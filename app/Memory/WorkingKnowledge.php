<?php

namespace App\Memory;

/**
 * Where a student's knowledge record lives in the memory tree (ledger D8).
 *
 * One document per subtopic, accumulating across skill levels -- a completed
 * 101 course is what a 201 course in the same subtopic starts from. There is
 * deliberately no per-course and no per-level variant.
 */
final class WorkingKnowledge
{
    public const FILENAME = 'WORKING_KNOWLEDGE.md';

    /**
     * The subtopic document every course in that subtopic reads and revises.
     */
    public static function forSubtopic(string $moduleSlug, string $subtopicSlug): string
    {
        return "{$moduleSlug}/{$subtopicSlug}/".self::FILENAME;
    }

    /**
     * The module rollup (spec 3.3) -- pointer lines, never materialised.
     */
    public static function forModule(string $moduleSlug): string
    {
        return "{$moduleSlug}/".self::FILENAME;
    }
}
