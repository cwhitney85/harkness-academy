<?php

namespace App\Memory;

/**
 * A document read from the tree, together with the version that holds it.
 *
 * The reason this exists rather than `get()` returning a string: a caller that
 * wants to record *which* text it read cannot ask a second question later. By
 * then another `put` may have landed, and the version it pins would not be the
 * version it used. Spec 4.5.4.1 pins the WK and CRITERIA a syllabus was built
 * from for exactly this reason (ledger D10.1).
 *
 * Invariant: `versionId` identifies the `memory_versions` row whose content is
 * `content` -- the two are read together, from that row.
 */
final readonly class MemoryDocument
{
    public function __construct(
        public string $path,
        public string $content,
        public int $versionId,
    ) {}
}
