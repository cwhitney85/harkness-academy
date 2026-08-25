<?php

namespace App\Lessons;

use App\Lessons\Exceptions\LessonSummaryUnusable;
use App\Models\Lesson;

/**
 * What one completed lesson established (spec 5.4.2).
 *
 * Two things read this: `wk.update`, which folds it into the student's
 * working-knowledge document, and `homework.generate`, which aims at what came
 * out shaky. A later refresher quiz reads the rendered SUMMARY.md instead
 * (5.4.3, 6.4).
 *
 * Invariant: every concept slug is declared by the lesson this summary belongs
 * to. That is 5.4.2's done-condition, and it is enforced here rather than in
 * the schema because the legal set differs per lesson -- JSON Schema can say
 * what a slug looks like, not which slugs this lesson taught.
 */
final readonly class LessonSummary
{
    /**
     * @param  array<string, mixed>  $document  a `json/lesson-summary.v1` document
     */
    private function __construct(private array $document) {}

    /**
     * Accept a model response as this lesson's summary.
     *
     * Pre: `$response` is the structured output of a `json/lesson-summary.v1`
     *      call, and `$sections` are the lesson's own.
     * Post: every concept slug is one the lesson declared, and no slug repeats.
     *
     * Fails **closed**, unlike a checkpoint. The completion is already
     * irrevocable, so nothing here can cost the student their progress; what a
     * loose summary would cost is the truth of every document downstream of it
     * (5.4.5 retries, then parks).
     *
     * @param  array<string, mixed>  $response
     *
     * @throws LessonSummaryUnusable
     */
    public static function fromModel(array $response, Lesson $lesson, LessonSections $sections): self
    {
        $summary = $response['summary'] ?? null;

        if (! is_array($summary) || ! is_array($summary['concepts'] ?? null)) {
            throw LessonSummaryUnusable::for($lesson, 'the model returned no structured summary.');
        }

        $declared = $sections->declaredConcepts();
        $undeclared = [];
        $seen = [];

        foreach ($summary['concepts'] as $concept) {
            $slug = (string) ($concept['slug'] ?? '');

            if (! in_array($slug, $declared, true)) {
                $undeclared[] = $slug;

                continue;
            }

            if (isset($seen[$slug])) {
                throw LessonSummaryUnusable::for($lesson, "concept [{$slug}] is reported twice.");
            }

            $seen[$slug] = true;
        }

        if ($undeclared !== []) {
            throw LessonSummaryUnusable::for($lesson, sprintf(
                'it reports concepts this lesson never declared: [%s]. Declared: [%s].',
                implode(', ', array_unique($undeclared)),
                implode(', ', $declared),
            ));
        }

        return new self(['summary' => $summary]);
    }

    /**
     * Read a stored summary back from `lessons.summary_json`.
     *
     * @param  array<string, mixed>  $document
     */
    public static function of(array $document): self
    {
        return new self($document);
    }

    /**
     * @return array<string, mixed>
     */
    public function document(): array
    {
        return $this->document;
    }

    public function prose(): string
    {
        return (string) $this->document['summary']['prose'];
    }

    /**
     * @return list<array{slug: string, mastery: Mastery, evidence: string}>
     */
    public function concepts(): array
    {
        return array_map(
            static fn (array $concept): array => [
                'slug' => (string) $concept['slug'],
                'mastery' => Mastery::from((string) $concept['mastery']),
                'evidence' => (string) $concept['evidence'],
            ],
            array_values($this->document['summary']['concepts']),
        );
    }

    /**
     * Every slug this summary reports, in the order reported.
     *
     * @return list<string>
     */
    public function slugs(): array
    {
        return array_map(
            static fn (array $concept): string => $concept['slug'],
            $this->concepts(),
        );
    }

    /**
     * @return list<string>
     */
    public function struggles(): array
    {
        return array_values(array_map(strval(...), $this->document['summary']['struggles'] ?? []));
    }

    /**
     * @return list<string>
     */
    public function questionsAsked(): array
    {
        return array_values(array_map(strval(...), $this->document['summary']['questions_asked'] ?? []));
    }

    /**
     * The JSON handed to `wk.update` and `homework.generate` as a prompt var.
     */
    public function encode(): string
    {
        return json_encode(
            $this->document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
