<?php

namespace App\Assessment;

/**
 * How wide a refresher casts (spec 6.4, 6.5).
 *
 * The three answers to "what am I being reminded of?", and the only thing that
 * differs between them is what documents the model reads and what row the
 * quiz hangs off. Everything after that -- generation, tag resolution, serving,
 * grading -- is one path.
 *
 * Ledger D12 left this open on purpose: "what a subtopic-grain refresher hangs
 * off is deliberately not decided ... `quizzable` is a morph; 6.4 decides what
 * goes in it."
 */
enum RefresherGrain: string
{
    /** One finished lesson, from its SUMMARY.md (spec 6.4). */
    case Lesson = 'lesson';

    /** One subtopic, from its WORKING_KNOWLEDGE.md and its lessons' summaries (spec 6.4). */
    case Subtopic = 'subtopic';

    /** One module, from the WK of every subtopic the rollup points at (spec 6.5). */
    case Module = 'module';
}
