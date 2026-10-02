<?php

namespace App\Admin;

use App\Admin\Exceptions\RestoreRefused;
use App\Courses\GenerationState;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\GenerationRequest;
use App\Models\Module;
use App\Models\Removal;
use App\Models\Subtopic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removing a module, subtopic or course, and restoring it (spec 16.2, ledger
 * D57).
 *
 * A removal stamps every row it hides with itself. Rows already removed are
 * left with their own removal (16.2.3), so restoring one removal brings back
 * exactly what it hid. What each level hides is the table in spec 16's "The
 * shape": a course takes its own documents and leaves the subtopic's working
 * knowledge, because what the student knows is still true.
 */
final class Removals
{
    public function __construct(
        private readonly ActionLog $log,
        private readonly MemoryTree $memory,
    ) {}

    public function remove(User $admin, Module|Subtopic|Course $subject, string $reason): Removal
    {
        return DB::transaction(function () use ($admin, $subject, $reason): Removal {
            $removal = Removal::query()->create([
                'admin_action_id' => $this->log->record($admin, AdminActionType::Removed, $subject, $reason)->id,
            ]);

            $stamp = ['deleted_at' => now(), 'removal_id' => $removal->id];

            // Top down, each level finding what to hide by the stamp the level
            // above it just took. The global scope skips rows already removed,
            // which is what leaves them their own removal.
            $subject->newQuery()->whereKey($subject->id)->update($stamp);

            if ($subject instanceof Module) {
                Subtopic::query()->where('module_id', $subject->id)->update($stamp);
            }

            if (! $subject instanceof Course) {
                Course::query()
                    ->whereIn('subtopic_id', Subtopic::onlyTrashed()->where('removal_id', $removal->id)->select('id'))
                    ->update($stamp);
            }

            $this->memory->remove($removal);

            // A job already queued for one of these courses finds nothing when
            // it starts and exits; what the student was waiting on closes
            // here (16.2.8).
            GenerationRequest::query()
                ->whereIn('course_id', Course::onlyTrashed()->where('removal_id', $removal->id)->select('id'))
                ->where('state', GenerationState::Open)
                ->update(['state' => GenerationState::Cancelled]);

            return $removal;
        });
    }

    /**
     * @throws RestoreRefused
     */
    public function restore(User $admin, Removal $removal, string $reason): void
    {
        $subject = $removal->subject();

        $refusal = $this->refusal($subject);

        if ($refusal !== null) {
            $this->log->record($admin, AdminActionType::RestoreRefused, $subject, $reason, [
                'removal_id' => $removal->id,
                'refusal' => $refusal,
            ]);

            throw new RestoreRefused($refusal);
        }

        DB::transaction(function () use ($admin, $removal, $subject, $reason): void {
            $unstamp = ['deleted_at' => null, 'removal_id' => null];

            foreach ([Module::class, Subtopic::class, Course::class] as $model) {
                $model::onlyTrashed()->where('removal_id', $removal->id)->update($unstamp);
            }

            $this->memory->restore($removal);

            $this->log->record($admin, AdminActionType::Restored, $subject, $reason, ['removal_id' => $removal->id]);
        });
    }

    /**
     * Why this removal cannot come back yet, in the admin's words, or null.
     *
     * A row cannot return under a parent that is still removed, and a course
     * cannot return while another course holds its subtopic's open slot
     * (16.2.6): the student started afresh, and two open courses would share
     * one working-knowledge document.
     */
    private function refusal(Module|Subtopic|Course $subject): ?string
    {
        $parent = match (true) {
            $subject instanceof Module => null,
            $subject instanceof Subtopic => $subject->module()->withTrashed()->first(),
            $subject instanceof Course => $subject->subtopic()->withTrashed()->first(),
        };

        if ($parent?->trashed()) {
            return 'Its '.strtolower(class_basename($parent)).", {$parent->title}, is removed. Restore that first.";
        }

        if (! $subject instanceof Course || ! $subject->isOpen()) {
            return null;
        }

        $open = Course::query()
            ->where('user_id', $subject->user_id)
            ->where('subtopic_id', $subject->subtopic_id)
            ->open()
            ->first();

        return $open === null ? null
            : "Course #{$open->id}, {$open->subtopic_title} {$open->skill_level->pathSegment()} attempt {$open->attempt}, is open on this subtopic.";
    }
}
