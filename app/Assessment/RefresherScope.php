<?php

namespace App\Assessment;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * What one refresher covers, and what the model may draw on (spec 6.4, 6.5).
 *
 * Carries the two prompt variables `quiz.refresher` declares -- `scope_docs`
 * and `scope_label` -- next to the three facts the row needs: what it hangs
 * off, whose it is, and which course anchors it.
 *
 * `vocabulary` is the hard part and the reason this is a value object rather
 * than two strings. The refresher prompt's one hard rule is "only ask about
 * what these documents actually record", and a prompt rule is not a guarantee.
 * The vocabulary is the same claim in a form code can check: every question's
 * concept tag has to be in it, or the quiz is regenerated. For a lesson that
 * set is the summary's own slugs, which is exactly what 6.4's done-condition
 * means by "traceable to its summary".
 */
final readonly class RefresherScope
{
    /**
     * @param  list<string>  $vocabulary  the concept slugs this scope records
     */
    public function __construct(
        public RefresherGrain $grain,
        public Model $quizzable,
        public User $student,
        public ?Course $course,
        public string $label,
        public string $documents,
        public array $vocabulary,
    ) {}

    /**
     * @return array<string, string>
     */
    public function promptVars(): array
    {
        return [
            'scope_docs' => $this->documents,
            'scope_label' => $this->label,
        ];
    }
}
