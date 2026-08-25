<?php

namespace App\Lessons\Checkpoints;

use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\Exceptions\CheckpointUnusable;
use App\Models\Quiz;

/**
 * One section's checkpoint, as a document (spec 5.2.2, 5.2.3).
 *
 * Two readers, two shapes, and this class is the only place that knows the
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
    public const ANSWER_KEY = ['correct_option_id', 'explanation'];

    /**
     * @param  array<string, mixed>  $document  a `json/checkpoint.v1` document
     */
    private function __construct(private array $document) {}

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
        $checkpoint = $response['checkpoint'] ?? null;

        if (! is_array($checkpoint) || ! is_array($checkpoint['questions'] ?? null)) {
            throw new CheckpointUnusable('The model returned no structured checkpoint.');
        }

        // The model is told which section it is writing about, but the index
        // is ours to know: a checkpoint filed under the wrong section would
        // be shown after the wrong prose.
        $checkpoint['section_index'] = $sectionIndex;

        $seen = [];

        foreach ($checkpoint['questions'] as $position => $question) {
            $id = (string) ($question['id'] ?? '');

            if ($id === '' || isset($seen[$id])) {
                throw new CheckpointUnusable("Question id [{$id}] is missing or repeated.");
            }

            $seen[$id] = true;

            $optionIds = array_map(
                static fn (array $option): string => (string) $option['id'],
                $question['options'],
            );

            if (count($optionIds) !== count(array_unique($optionIds))) {
                throw new CheckpointUnusable("Question [{$id}] repeats an option id.");
            }

            // Grading compares the student's option id to this one, so a key
            // pointing at nothing makes every answer wrong forever.
            if (! in_array((string) $question['correct_option_id'], $optionIds, true)) {
                throw new CheckpointUnusable("Question [{$id}] keys an option it does not offer.");
            }

            $checkpoint['questions'][$position]['concept_tag'] = self::resolveTag($question['concept_tag'] ?? null, $concepts);
        }

        return new self(['checkpoint' => $checkpoint]);
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
        return new self($quiz->payload);
    }

    public function sectionIndex(): int
    {
        return (int) $this->document['checkpoint']['section_index'];
    }

    /**
     * The stored form -- answer key included.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->document;
    }

    /**
     * The streamed form (spec 5.2.3) -- answer key removed.
     *
     * @return array<string, mixed>
     */
    public function clientPayload(): array
    {
        $document = $this->document;

        foreach ($document['checkpoint']['questions'] as $position => $question) {
            $document['checkpoint']['questions'][$position] = array_diff_key(
                $question,
                array_flip(self::ANSWER_KEY),
            );
        }

        return $document;
    }

    /**
     * One question by id, or null if this checkpoint does not ask it.
     *
     * @return array<string, mixed>|null
     */
    public function question(string $questionId): ?array
    {
        foreach ($this->document['checkpoint']['questions'] as $question) {
            if ((string) $question['id'] === $questionId) {
                return $question;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        return array_values($this->document['checkpoint']['questions']);
    }

    /**
     * Keep a tag the syllabus declares; drop one it does not.
     *
     * 5.2.2 makes the tag a SHOULD -- "droppable without breaking the loop".
     * An invented slug would otherwise flow into the summary and from there
     * into a refresher quiz keyed on a concept no lesson ever taught, which is
     * a worse outcome than one untagged question.
     */
    private static function resolveTag(mixed $tag, SyllabusConcepts $concepts): ?string
    {
        if (! is_string($tag) || $tag === '') {
            return null;
        }

        return $concepts->has($tag) ? $tag : null;
    }
}
