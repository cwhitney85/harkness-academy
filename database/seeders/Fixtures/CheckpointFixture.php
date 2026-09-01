<?php

namespace Database\Seeders\Fixtures;

/**
 * One section's checkpoint, and what the student did with it (spec 8.2.4).
 *
 * `answers` empty is the state 8.2.4 asks for: a checkpoint that has arrived
 * and not been answered. It is the case that matters, because it is the one
 * where the served payload must still be missing its answer key (5.2.3) --
 * an answered question has already been told what it was.
 */
final readonly class CheckpointFixture
{
    /**
     * @param  list<array<string, mixed>>  $questions  `json/checkpoint.v1` questions, answer key included
     * @param  array<string, string>  $answers  question id => chosen option id
     */
    public function __construct(
        public int $sectionIndex,
        public array $questions,
        public array $answers = [],
    ) {}

    /**
     * The stored document, exactly as the model would have produced it.
     *
     * `section_index` is set here rather than left to the caller because the
     * engine sets it too (Checkpoint::fromModel) -- a fixture whose payload
     * disagreed with its column would be a state the engine cannot produce.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'checkpoint' => [
                'section_index' => $this->sectionIndex,
                'questions' => $this->questions,
            ],
        ];
    }
}
