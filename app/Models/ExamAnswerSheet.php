<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A sitting of the final exam, half done (spec 6.3.4, ledger D26).
 *
 * Selections only. Nothing derived from the answer key is stored here and
 * nothing here is graded: the row is what the student has ticked so far, and
 * it is deleted the moment the book is handed in, so a re-sit opens empty.
 *
 * @property int $id
 * @property int $quiz_id
 * @property int $user_id
 * @property int $attempt
 * @property array<string, string> $selections
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class ExamAnswerSheet extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'selections' => 'array',
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
