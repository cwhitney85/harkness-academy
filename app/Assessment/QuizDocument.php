<?php

namespace App\Assessment;

use App\Models\Quiz;

/**
 * A stored quiz, read (spec 6.1).
 *
 * One reader for both contracts. `json/checkpoint.v1` and `json/quiz.v1`
 * differ in their envelope key and in the two fields only a quiz carries
 * (`scope`, `suggested_reading`); everything a reader does with them --
 * finding a question, stripping the key, listing the concepts -- is identical.
 * Writing that twice is how a stripping rule gets fixed in one place and left
 * broken in the other.
 *
 * Two shapes, and this class is the only thing that knows the difference:
 *   - `payload()` is what `quizzes.payload` stores -- answer key included,
 *     byte-for-byte what the model produced, so 5.2.2's lossless round-trip
 *     holds.
 *   - `clientPayload()` is what goes over the wire -- `correct_option_id` and
 *     `explanation` removed from every question.
 *
 * There is deliberately no flag that switches between them. A single method
 * taking `$includeAnswers` is one wrong argument away from serving the answer
 * key, and 5.2.3's done-condition is that inspecting the stream finds no
 * pre-answer key at all.
 */
final readonly class QuizDocument
{
    /**
     * The two fields a student may not see until they have answered -- and,
     * for a final exam, may not see at all (QuizKind::disclosesAnswerKey).
     *
     * @var list<string>
     */
    public const ANSWER_KEY = ['correct_option_id', 'explanation'];

    /**
     * @param  array<string, mixed>  $document
     */
    private function __construct(private array $document, private string $envelope) {}

    public static function of(Quiz $quiz): self
    {
        return new self($quiz->payload, $quiz->kind->envelope());
    }

    /**
     * A document the model just produced, not yet stored.
     *
     * @param  array<string, mixed>  $document
     */
    public static function in(array $document, QuizKind $kind): self
    {
        return new self($document, $kind->envelope());
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
     * What the envelope wraps.
     *
     * @return array<string, mixed>
     */
    public function body(): array
    {
        $body = $this->document[$this->envelope] ?? null;

        return is_array($body) ? $body : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function questions(): array
    {
        $questions = $this->body()['questions'] ?? null;

        return is_array($questions) ? array_values($questions) : [];
    }

    /**
     * One question by id, or null if this quiz does not ask it.
     *
     * @return array<string, mixed>|null
     */
    public function question(string $questionId): ?array
    {
        foreach ($this->questions() as $question) {
            if ((string) $question['id'] === $questionId) {
                return $question;
            }
        }

        return null;
    }

    /**
     * What this quiz says it covers, in the student's words.
     *
     * A checkpoint has none -- its scope is the section it closes, which is a
     * column rather than a sentence -- and says so with an empty string.
     */
    public function scope(): string
    {
        return (string) ($this->body()['scope'] ?? '');
    }

    /**
     * The reading a homework quiz carries alongside its questions (spec 6.2).
     *
     * Always a list, empty when the model had nothing genuinely useful to
     * suggest and for every kind that carries none.
     *
     * @return list<array{title: string, why: string}>
     */
    public function suggestedReading(): array
    {
        $reading = $this->body()['suggested_reading'] ?? null;

        if (! is_array($reading)) {
            return [];
        }

        return array_values(array_map(
            static fn (array $item): array => [
                'title' => (string) $item['title'],
                'why' => (string) $item['why'],
            ],
            $reading,
        ));
    }

    /**
     * The concept slugs this quiz tags its questions with, deduped, in order.
     *
     * @return list<string>
     */
    public function conceptTags(): array
    {
        $tags = [];

        foreach ($this->questions() as $question) {
            $tag = $question['concept_tag'] ?? null;

            if (is_string($tag) && $tag !== '') {
                $tags[$tag] = true;
            }
        }

        return array_keys($tags);
    }

    /**
     * The served form (spec 5.2.3) -- answer key removed from every question.
     *
     * @return array<string, mixed>
     */
    public function clientPayload(): array
    {
        $document = $this->document;
        $questions = [];

        foreach ($this->questions() as $question) {
            $questions[] = array_diff_key($question, array_flip(self::ANSWER_KEY));
        }

        $document[$this->envelope]['questions'] = $questions;

        return $document;
    }

    /**
     * What stops this document being served or graded, if anything.
     *
     * Reports rather than throws. The checkpoint path fails **open** (5.2.1)
     * and raises its own exception; the exam path fails closed. A document
     * that decided which would be deciding a policy that belongs to its
     * caller (spec 4.5.1's rule is per-caller).
     *
     * Post: an empty list means every question has a unique id, distinct
     *       option ids, and a `correct_option_id` naming one of its own
     *       options -- the three things a schema cannot state and grading
     *       cannot survive without. A key pointing at nothing would make
     *       every answer to that question wrong forever.
     *
     * @return list<string>
     */
    public function defects(): array
    {
        $questions = $this->questions();

        if ($questions === []) {
            return ['It asks no questions.'];
        }

        $defects = [];
        $seen = [];

        foreach ($questions as $question) {
            $id = (string) ($question['id'] ?? '');

            if ($id === '' || isset($seen[$id])) {
                $defects[] = "Question id [{$id}] is missing or repeated.";

                continue;
            }

            $seen[$id] = true;

            $optionIds = array_map(
                static fn (array $option): string => (string) $option['id'],
                $question['options'] ?? [],
            );

            if (count($optionIds) !== count(array_unique($optionIds))) {
                $defects[] = "Question [{$id}] repeats an option id.";
            }

            if (! in_array((string) ($question['correct_option_id'] ?? ''), $optionIds, true)) {
                $defects[] = "Question [{$id}] keys an option it does not offer.";
            }
        }

        return $defects;
    }

    /**
     * The same document with every concept tag the vocabulary does not declare
     * dropped to null.
     *
     * 5.2.2 makes the tag a SHOULD -- "droppable without breaking the loop".
     * An invented slug would otherwise flow into a summary and from there into
     * a refresher quiz keyed on a concept no lesson ever taught, which is a
     * worse outcome than one untagged question.
     *
     * The vocabulary is a plain list rather than a SyllabusConcepts because
     * the legal set is not always one syllabus's: a subtopic-grain refresher
     * resolves against every course in the subtopic (6.4), the way
     * ErosionGuard does.
     *
     * @param  list<string>  $vocabulary
     */
    public function withResolvedTags(array $vocabulary): self
    {
        $document = $this->document;
        $questions = [];

        foreach ($this->questions() as $question) {
            $tag = $question['concept_tag'] ?? null;

            $question['concept_tag'] = is_string($tag) && in_array($tag, $vocabulary, true) ? $tag : null;

            $questions[] = $question;
        }

        $document[$this->envelope]['questions'] = $questions;

        return new self($document, $this->envelope);
    }

    /**
     * The same document with each question's correct option moved to a
     * position this code chose rather than the model (spec 6.6).
     *
     * A model asked for one correct option among four puts it first or second
     * far more often than chance, and a student who notices stops reading the
     * options. The key is moved and nothing else is: the distractors keep the
     * order they were written in, so an option that reads as a closing
     * alternative still closes.
     *
     * Positions are handed out by least-used-first within the document, ties
     * broken at random, rather than by shuffling each question independently.
     * A per-question shuffle is uniform only in expectation, which is a
     * property no test can assert and no student can rely on; least-used-first
     * makes an even spread a fact about every quiz, and the random tie-break
     * is what stops the spread being a pattern -- a rotation would teach the
     * student that question two's answer is B.
     *
     * Post: every question offers the same options with the same ids and the
     *       same `correct_option_id`; only their order has changed.
     */
    public function withDistributedAnswers(): self
    {
        /** @var array<int, int> $used */
        $used = [];
        $document = $this->document;
        $questions = [];

        foreach ($this->questions() as $question) {
            $options = array_values($question['options'] ?? []);
            $key = (string) ($question['correct_option_id'] ?? '');

            $from = null;

            foreach ($options as $index => $option) {
                if ((string) $option['id'] === $key) {
                    $from = $index;
                }
            }

            // A question whose key names no option of its own is a defect
            // `defects()` reports and every caller refuses on; there is
            // nothing here to move, and inventing a position would hide it.
            if ($from === null) {
                $questions[] = $question;

                continue;
            }

            $to = $this->leastUsedPosition(count($options), $used);
            $used[$to] = ($used[$to] ?? 0) + 1;

            [$correct] = array_splice($options, $from, 1);
            array_splice($options, $to, 0, [$correct]);

            $question['options'] = $options;
            $questions[] = $question;
        }

        $document[$this->envelope]['questions'] = $questions;

        return new self($document, $this->envelope);
    }

    /**
     * The position this question should key, among the ones it offers.
     *
     * @param  array<int, int>  $used  how many questions have keyed each position so far
     */
    private function leastUsedPosition(int $options, array $used): int
    {
        $candidates = [];
        $fewest = null;

        for ($position = 0; $position < $options; $position++) {
            $count = $used[$position] ?? 0;

            if ($fewest === null || $count < $fewest) {
                $fewest = $count;
                $candidates = [];
            }

            if ($count === $fewest) {
                $candidates[] = $position;
            }
        }

        return $candidates[array_rand($candidates)];
    }

    /**
     * The same document with one field set on its body.
     *
     * The one write a reader is allowed: a checkpoint's `section_index` is
     * ours to know rather than the model's to claim (5.2.2).
     */
    public function withBodyField(string $key, mixed $value): self
    {
        $document = $this->document;
        $document[$this->envelope][$key] = $value;

        return new self($document, $this->envelope);
    }
}
