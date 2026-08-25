<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One answer a student gave (spec 5.2.4, 6.1).
 *
 * Rows accumulate rather than overwrite: a checkpoint never gates Continue, so
 * answering the same question again is ordinary, and `attempt` keeps both
 * answers. What the summarizer reads is the first attempt per question -- that
 * is the one that says what the student knew before being told.
 *
 * @property int $id
 * @property int $quiz_id
 * @property int $user_id
 * @property string $question_id
 * @property string $option_id
 * @property bool $correct
 * @property int $attempt
 * @property Carbon $created_at
 */
class QuizAttempt extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'correct' => 'boolean',
            'attempt' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }
}
