<?php

namespace App\Lessons\Checkpoints;

use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\Exceptions\CheckpointUnusable;
use App\Models\Quiz;

/**
 * One section's checkpoint, as a document (spec 5.2.2, 5.2.3).
 *
 * The `json/checkpoint.v1` half of `App\Assessment\QuizDocument`: reading a
 * quiz is the same job whichever kind it is (6.1), and what remains here is
 * the two things only a checkpoint has -- the section it closes, and 5.2.1's
 * fail-open acceptance rule.
 *
 * Two readers, two shapes, and QuizDocument is the only place that knows the
 * difference:
 *   - `payload()` is what `quizzes.payload` stores -- answer key included,
 *     byte-for-byte what the model produced, so 5.2.2's lossless round-trip
 *     holds.
 *   - `clientPayload()` is what goes over the wire -- `correct_option_id` and
 *     `explanation` removed from every question.
 *
 * There is deliberately no flag that switches between them. A single method
 * taking `$includeAnswers` is one wrong argument away from streaming the
 * answer key, and 5.2.3's done-condition is that inspecting the stream finds
 * no pre-answer key at all.
 */
final readonly class Checkpoint
{
    /**
     * The two fields a student may not see until they have answered.
     *
     * @var list<string>
     */
    public const ANSWER_KEY = QuizDocument::ANSWER_KEY;

    private function __construct(private QuizDocument $document) {}

    /**
     * Accept a model response as a checkpoint for this section.
     *
     * Pre: `$response` is the structured output of a `json/checkpoint.v1`
     *      call. The schema has already enforced shape; what it cannot state
     *      is enforced here.
     * Post: every question has a `correct_option_id` naming one of its own
     *       options, ids are unique within the checkpoint, `section_index` is
     *       the section actually taught, and every surviving `concept_tag` is
     *       declared by the syllabus.
     *
     * @param  array<string, mixed>  $response
     *
     * @throws CheckpointUnusable
     */
    public static function fromModel(array $response, int $sectionIndex, SyllabusConcepts $concepts): self
    {
        $document = QuizDocument::in($response, QuizKind::Checkpoint);

        $defects = $document->defects();

        if ($defects !== []) {
            throw new CheckpointUnusable(implode(' ', $defects));
        }

        return new self(
            // The model is told which section it is writing about, but the
            // index is ours to know: a checkpoint filed under the wrong
            // section would be shown after the wrong prose.
            $document->withBodyField('section_index', $sectionIndex)
                ->withResolvedTags($concepts->declared()),
        );
    }

    /**
     * Read a stored checkpoint back.
     *
     * The round-trip of 5.2.2: what went into `quizzes.payload` comes back
     * out unchanged, which is why generation is never repeated for a section
     * that already has one.
     */
    public static function of(Quiz $quiz): self
    {
        return new self(QuizDocument::of($quiz));
    }

    public function sectionIndex(): int
    {
        return (int) $this->document->body()['section_index'];
    }

    /**
     * The stored form -- answer key included.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->document->payload();
    }

    /**
     * The streamed form (spec 5.2.3) -- answer key removed.
     *
     * @return array<string, mixed>
     */
    public function clientPayload(): array
    {
        return $this->document->clientPayload();
    }

    /**
     * One question by id, or null if this checkpoint does not ask it.
     *
     * @return array<string, mixed>|null
     */
    public function question(string $questionId): ?array
    {
        return $this->document->question($questionId);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        return $this->document->questions();
    }
}
