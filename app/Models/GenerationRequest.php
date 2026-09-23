<?php

namespace App\Models;

use App\Courses\GenerationKind;
use App\Courses\GenerationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * A generation the student asked for (ledger D25, spec 11), kept once it is
 * over (16.2.11, D68).
 *
 * `state` is the answer to "is this being drawn up?" -- a screen reads the
 * course's latest request for a kind and asks whether it is still pending.
 * Failure carries the sentence the student is owed, because the generators
 * fail closed and there is no longer a response to say so in.
 *
 * @property int $id
 * @property int $course_id
 * @property GenerationKind $kind
 * @property GenerationState $state
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
     * Post: a new open request exists. Returns null when one was already
     *       pending, which is the caller's signal to dispatch nothing -- a
     *       second press must not buy a second call.
     *
     * A failed request is followed by a new one rather than refused: the
     * failure is the whole reason the student is pressing again. One still
     * open past its kind's patience is closed as expired first.
     */
    public static function open(Course $course, GenerationKind $kind): ?self
    {
        $latest = self::for($course, $kind);

        if ($latest?->isPending()) {
            return null;
        }

        if ($latest?->state === GenerationState::Open) {
            $latest->forceFill(['state' => GenerationState::Expired])->save();
        }

        try {
            return self::query()->create([
                'course_id' => $course->id,
                'kind' => $kind,
                'state' => GenerationState::Open,
                'requested_at' => now(),
            ]);
        } catch (QueryException) {
            // Two presses landed together and the one-open-row index took the
            // second. The first is out; this one asked for nothing.
            return null;
        }
    }

    /**
     * The course's latest request for this kind, whatever its state.
     */
    public static function for(Course $course, GenerationKind $kind): ?self
    {
        return self::query()
            ->where('course_id', $course->id)
            ->where('kind', $kind)
            ->latest('id')
            ->first();
    }

    /**
     * The work landed: what was asked for now exists.
     */
    public function settle(): void
    {
        $this->forceFill(['state' => GenerationState::Landed])->save();
    }

    /**
     * The generator gave up, having written nothing (4.5.1, 6.3).
     *
     * Post: the row carries what the student should be told. The next press
     *       opens a new request after it.
     */
    public function fail(string $studentMessage): void
    {
        $this->forceFill([
            'state' => GenerationState::Failed,
            'failed_at' => now(),
            'failure' => $studentMessage,
        ])->save();
    }

    public function hasFailed(): bool
    {
        return $this->state === GenerationState::Failed;
    }

    /**
     * Whether a screen should still say the work is coming.
     *
     * Only while it is open, and not once it has been out longer than its
     * kind will wait (`GenerationKind::patience`).
     */
    public function isPending(): bool
    {
        $patience = $this->kind->patience();

        return $this->state === GenerationState::Open
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
            'state' => GenerationState::class,
            'requested_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
