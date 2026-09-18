<?php

namespace Tests\Feature\Admin;

use App\Admin\ActionLog;
use App\Admin\AdminActionType;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Spec 13.2.1 -- the action log only grows. */
class ActionLogTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_recorded_action_names_its_actor_subject_reason_and_payload(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();

        $row = app(ActionLog::class)->record($admin, AdminActionType::ViewAsSteppedUp, $student, 'reproducing a bug', ['mode' => 'act_as']);

        $row->refresh();
        $this->assertTrue($row->actor->is($admin));
        $this->assertTrue($row->subject->is($student));
        $this->assertSame(AdminActionType::ViewAsSteppedUp, $row->action);
        $this->assertSame('reproducing a bug', $row->reason);
        $this->assertSame(['mode' => 'act_as'], $row->payload);
    }

    #[Test]
    public function no_row_can_be_updated_or_deleted_by_any_path(): void
    {
        $row = app(ActionLog::class)->record(User::factory()->admin()->create(), AdminActionType::ViewAsStarted);

        foreach ([
            'eloquent update' => fn () => $row->update(['reason' => 'rewritten']),
            'eloquent delete' => fn () => $row->delete(),
            'builder update' => fn () => AdminAction::query()->update(['reason' => 'rewritten']),
            'builder delete' => fn () => AdminAction::query()->delete(),
        ] as $path => $write) {
            DB::beginTransaction();

            try {
                $write();
                $this->fail("The {$path} went through.");
            } catch (QueryException $e) {
                $this->assertStringContainsString('append-only', $e->getMessage(), $path);
            } finally {
                DB::rollBack();
            }
        }

        $this->assertNull($row->refresh()->reason);
        $this->assertSame(1, AdminAction::query()->count());
    }
}
