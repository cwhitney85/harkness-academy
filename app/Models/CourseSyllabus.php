<?php

namespace App\Models;

use Database\Factories\CourseSyllabusFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One syllabus a course was shown (spec 4.5.4.1, ledger D10.1).
 *
 * Rows are written by the syllabus workflow and never edited afterwards: a
 * revision is a new row, and approval only ever stamps `approved_at`. That is
 * what makes the approved syllabus immutable, which 4.5.3 relies on when it
 * materialises GUIDANCE.md from it.
 *
 * `wk_version_id` and `criteria_version_id` are plain integers here on
 * purpose. They point into `memory_versions`, and spec 3.2 makes the path API
 * the only door to memory -- so this model records the identity of what was
 * read and never dereferences it.
 *
 * @property int $id
 * @property int $course_id
 * @property int $generation
 * @property array<string, mixed> $syllabus_json
 * @property string|null $feedback
 * @property int $wk_version_id
 * @property int $criteria_version_id
 * @property Carbon|null $approved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class CourseSyllabus extends Model
{
    /** @use HasFactory<CourseSyllabusFactory> */
    use HasFactory;

    /**
     * Latin plural: Eloquent would guess `course_syllabuses`.
     */
    protected $table = 'course_syllabi';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'generation' => 'integer',
            'syllabus_json' => 'array',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The one generation the student accepted, if they have yet.
     *
     * @param  Builder<CourseSyllabus>  $query
     */
    public function scopeApproved(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    /**
     * Whether this row is a revision rather than the original draft.
     *
     * Generation 1 is the first generation, which nobody asked for changes to
     * (ledger D10.1) -- so it is the one row with no feedback behind it.
     */
    public function isRevision(): bool
    {
        return $this->generation > 1;
    }
}
