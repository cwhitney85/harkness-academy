<?php

namespace App\Models;

use App\Courses\GenerationKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * A generation the student is waiting on (ledger D25, spec 11).
 *
 * The row is the wait. It exists from the press of the button until the thing
 * asked for exists, and then it is gone -- so a screen asking "is this being
 * drawn up?" is asking whether a row is here, and never has to reconcile two
 * sources for one fact.
 *
 * The one state that outlives the work is failure, because the generators fail
 * closed and there is no longer a response to say so in.
 *
 * @property int $id
 * @property int $course_id
 * @property GenerationKind $kind
 * @property Carbon $requested_at
 * @property Carbon|null $failed_at
 * @property string|null $failure
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class GenerationRequest extends Model
{
    protected $guarded = [];

    /**
     * Ask for a generation, unless one is already out.
     *
     * Pre:  none. The caller has already decided the student may ask.
     * Post: a request row exists, carrying no failure. Returns null when a
     *       request was already pending, which is the caller's signal to
     *       dispatch nothing -- a second press must not buy a second call.
     *
     * A failed or abandoned request is reopened rather than refused: the
     * failure is the whole reason the student is pressing again.
     */
    public static function open(Course $course, GenerationKind $kind): ?self
    {
        $existing = self::for($course, $kind);

        if ($existing instanceof self && $existing->isPending()) {
            return null;
        }

        if ($existing instanceof self) {
            $existing->forceFill([
                'requested_at' => now(),
                'failed_at' => null,
                'failure' => null,
            ])->save();

            return $existing;
        }

        try {
            return self::query()->create([
                'course_id' => $course->id,
                'kind' => $kind,
                'requested_at' => now(),
            ]);
        } catch (QueryException) {
            // Two presses landed together and the unique index took the
            // second. The first is out; this one asked for nothing.
            return null;
        }
    }

    public static function for(Course $course, GenerationKind $kind): ?self
    {
        return self::query()
            ->where('course_id', $course->id)
            ->where('kind', $kind)
            ->first();
    }

    /**
     * The work landed. What was asked for now exists, so the wait does not.
     */
    public function settle(): void
    {
        $this->delete();
    }

    /**
     * The generator gave up, having written nothing (4.5.1, 6.3).
     *
     * Post: the row stays, carrying what the student should be told. The next
     *       press clears it by reopening.
     */
    public function fail(string $studentMessage): void
    {
        $this->forceFill([
            'failed_at' => now(),
            'failure' => $studentMessage,
        ])->save();
    }

    public function hasFailed(): bool
    {
        return $this->failed_at !== null;
    }

    /**
     * Whether a screen should still say the work is coming.
     *
     * Not once it has failed, and not once it has been out longer than its
     * kind will wait (`GenerationKind::patience`).
     */
    public function isPending(): bool
    {
        $patience = $this->kind->patience();

        return ! $this->hasFailed()
            && ($patience === null || $this->requested_at->isAfter(now()->subSeconds($patience)));
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => GenerationKind::class,
            'requested_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
