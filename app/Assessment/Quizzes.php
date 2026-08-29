<?php

namespace App\Assessment;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The single writer of `quizzes` (spec 6.1).
 *
 * All four kinds are created here and nowhere else -- the same rule MemoryTree
 * holds for memory and CourseTransitions holds for `courses.status`. Package 5
 * lands the schema and 6.1 lands this service over it (ledger D12); before it
 * existed, the checkpoint generator and the homework job each carried their
 * own copy of the create-or-adopt race below, which is one copy away from a
 * third kind getting it subtly wrong.
 *
 * Invariant: `section_index` is set if and only if the kind is section-scoped,
 * and `user_id`/`course_id` are taken from the anchoring course rather than
 * from the caller -- a quiz filed against someone else's course is not a state
 * this service can be talked into.
 */
final class Quizzes
{
    /**
     * Write a quiz, or adopt the one that beat us to it.
     *
     * Pre: `$sectionIndex` is set exactly when `$kind->isSectionScoped()`, and
     *      `$payload` is the document the model produced -- answer key intact,
     *      because grading is code and reads it back from this column
     *      (ledger D5).
     * Post: exactly one `quizzes` row exists for this scope, and it is the one
     *       returned. For a repeatable kind (6.4's refreshers) "this scope"
     *       means this call: a second refresher over the same lesson is a
     *       second row on purpose.
     *
     * Two clients reconnecting to the same freshly-taught section can both
     * reach generation before either has written. `quizzes_one_per_scope`
     * decides, and the loser reads the winner's row rather than raising at the
     * student (5.2.6).
     *
     * @param  array<string, mixed>  $payload
     */
    public function create(
        QuizKind $kind,
        Model $quizzable,
        User $student,
        ?Course $course,
        array $payload,
        ?int $sectionIndex = null,
    ): Quiz {
        if ($kind->isSectionScoped() !== ($sectionIndex !== null)) {
            throw new InvalidArgumentException(
                "A [{$kind->value}] quiz "
                    .($kind->isSectionScoped() ? 'needs a section index.' : 'has no section to index.'),
            );
        }

        if ($course instanceof Course && $course->user_id !== $student->id) {
            throw new InvalidArgumentException("Course [{$course->id}] does not belong to this student.");
        }

        $attributes = [
            'user_id' => $student->id,
            'course_id' => $course?->id,
            'kind' => $kind,
            'quizzable_type' => $quizzable->getMorphClass(),
            'quizzable_id' => $quizzable->getKey(),
            'section_index' => $sectionIndex,
            'payload' => $payload,
        ];

        try {
            // Wrapped so the losing insert cannot poison a transaction the
            // caller opened. Postgres aborts the whole transaction on a
            // constraint violation, so without a savepoint here the adopting
            // SELECT below would fail with "current transaction is aborted"
            // -- and the loser would raise at the student after all.
            return DB::transaction(fn (): Quiz => Quiz::query()->create($attributes));
        } catch (QueryException $e) {
            // A repeatable kind is not held to one per scope, so a failure
            // here is a real one -- there is no winner to adopt.
            if ($kind->isRepeatable()) {
                throw $e;
            }

            $winner = $this->existing($kind, $quizzable, $sectionIndex);

            if ($winner instanceof Quiz) {
                return $winner;
            }

            throw $e;
        }
    }

    /**
     * The one quiz of this kind a scope already has, if any.
     *
     * Pre: the kind is not repeatable -- a scope may hold any number of
     *      refreshers, and "the" one is not a question with an answer. Ask
     *      `latest()` instead.
     */
    public function existing(QuizKind $kind, Model $quizzable, ?int $sectionIndex = null): ?Quiz
    {
        if ($kind->isRepeatable()) {
            throw new InvalidArgumentException(
                "A [{$kind->value}] scope may hold more than one quiz; ask for the latest.",
            );
        }

        return $this->scoped($kind, $quizzable, $sectionIndex)->first();
    }

    /**
     * The most recently written quiz of this kind for a scope.
     *
     * What a repeatable kind is served from: the refresher a student is
     * currently taking is the last one generated for that scope (6.4).
     */
    public function latest(QuizKind $kind, Model $quizzable): ?Quiz
    {
        return $this->scoped($kind, $quizzable)->latest('id')->first();
    }

    /**
     * Every quiz of this kind for a scope, oldest first.
     *
     * @return list<Quiz>
     */
    public function all(QuizKind $kind, Model $quizzable): array
    {
        return array_values($this->scoped($kind, $quizzable)->orderBy('id')->get()->all());
    }

    /**
     * @return Builder<Quiz>
     */
    private function scoped(QuizKind $kind, Model $quizzable, ?int $sectionIndex = null): Builder
    {
        $query = Quiz::query()
            ->ofKind($kind)
            ->where('quizzable_type', $quizzable->getMorphClass())
            ->where('quizzable_id', $quizzable->getKey());

        if ($sectionIndex !== null) {
            $query->where('section_index', $sectionIndex);
        }

        return $query;
    }
}
