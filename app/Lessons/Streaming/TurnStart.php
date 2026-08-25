<?php

namespace App\Lessons\Streaming;

/**
 * A turn has begun (spec 5.2.5).
 *
 * The three section fields travel together or not at all: they exist so a
 * client can draw "Section 2 of 4 — Undoing an operation" without a second
 * request, and a `qa` or `explainer` turn has no section to name. Sending
 * them as nulls would make every client write the same null check.
 */
final readonly class TurnStart implements StreamEvent
{
    public function __construct(
        public string $turnId,
        public TurnKind $kind,
        public ?int $sectionIndex = null,
        public ?string $sectionTitle = null,
        public ?int $sectionsTotal = null,
    ) {}

    public function name(): string
    {
        return 'turn.start';
    }

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        $data = ['turn_id' => $this->turnId, 'kind' => $this->kind->value];

        if ($this->sectionIndex !== null) {
            $data['section_index'] = $this->sectionIndex;
            $data['section_title'] = $this->sectionTitle;
            $data['sections_total'] = $this->sectionsTotal;
        }

        return $data;
    }
}
