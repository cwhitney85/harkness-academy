<?php

namespace App\Models;

use App\Assessment\QuizKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One generated quiz, of any of the four kinds (spec 6.1).
 *
 * `payload` holds the model's document with its answer key intact. Nothing
 * hands this column to a client: 5.2.3 strips `correct_option_id` and
 * `explanation` server-side until a question is answered, and the stripping
 * lives on the value objects that read this column, not here.
 *
 * @property int $id
 * @property int $user_id
 * @property int|null $course_id
 * @property QuizKind $kind
 * @property string $quizzable_type
 * @property int $quizzable_id
 * @property int|null $section_index
 * @property array<string, mixed> $payload
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class Quiz extends Model
{
    protected $table = 'quizzes';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => QuizKind::class,
            'section_index' => 'integer',
            'payload' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function quizzable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<QuizAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * @param  Builder<Quiz>  $query
     */
    public function scopeOfKind(Builder $query, QuizKind $kind): void
    {
        $query->where('kind', $kind->value);
    }
}
