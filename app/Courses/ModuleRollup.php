<?php

namespace App\Courses;

use App\Memory\MemoryTree;
use App\Memory\WorkingKnowledge;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;

/**
 * The derived module rollup (spec 3.3).
 *
 * `{module}/WORKING_KNOWLEDGE.md` is never written. It is rendered on demand
 * from two sources, and the split matters:
 *
 * - **Which subtopics appear** comes from the memory tree. A student who
 *   withdrew still has a real knowledge record from their interview, and
 *   keying this off completed courses would hide it.
 * - **Which levels are named** comes from the courses table, because
 *   "completed 101" is a fact about a course, not about knowledge (D8).
 *
 * Nothing writes, so nothing can drift: completing a course changes the
 * rollup on the next read with no write step anywhere.
 *
 * Deliberately *not* served through `MemoryTree::get()`. That method's
 * contract is "returns what `put` wrote, or null"; synthesising content there
 * would break it, and would point package 3 at package 4's tables.
 */
final class ModuleRollup
{
    public function __construct(private readonly MemoryTree $tree) {}

    /**
     * The rollup's lines, for code that walks them (spec 6.5's module quiz)
     * rather than reading the prose.
     *
     * @return list<SubtopicPointer>
     */
    public function pointers(User $student, Module $module): array
    {
        $levels = $this->levelsCompleted($student, $module);
        $titles = $this->titles($student, $module);

        $pointers = [];

        foreach ($this->subtopicsWithKnowledge($student, $module) as $slug) {
            $pointers[] = new SubtopicPointer(
                path: WorkingKnowledge::forSubtopic($module->slug, $slug),
                subtopicSlug: $slug,
                title: $titles[$slug] ?? $slug,
                levelsCompleted: $levels[$slug] ?? [],
            );
        }

        return $pointers;
    }

    /**
     * The document a model reads at `{module}/WORKING_KNOWLEDGE.md`.
     */
    public function render(User $student, Module $module): string
    {
        $pointers = $this->pointers($student, $module);

        $lines = [
            "# {$module->title} — what this student knows",
            '',
        ];

        if ($pointers === []) {
            $lines[] = "No {$module->title} subtopics have a knowledge record yet.";

            return implode("\n", $lines)."\n";
        }

        $lines[] = 'One line per subtopic. Read the records you need; do not assume their contents.';
        $lines[] = '';

        foreach ($pointers as $pointer) {
            $lines[] = sprintf('- `%s` — %s (%s)', $pointer->path, $pointer->title, $this->describe($pointer));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Subtopics with a knowledge document, from the tree.
     *
     * @return list<string>
     */
    private function subtopicsWithKnowledge(User $student, Module $module): array
    {
        $slugs = [];

        foreach ($this->tree->list($student, $module->slug) as $path) {
            $segments = explode('/', $path);

            // Exactly {module}/{subtopic}/WORKING_KNOWLEDGE.md. Depth 2 would
            // be this rollup itself, which nothing should ever have written.
            if (count($segments) === 3 && $segments[2] === WorkingKnowledge::FILENAME) {
                $slugs[] = $segments[1];
            }
        }

        return $slugs;
    }

    /**
     * Skill levels the student has completed, per subtopic (ledger D8's rider:
     * one line per subtopic naming the levels, never one line per course).
     *
     * @return array<string, list<SkillLevel>>
     */
    private function levelsCompleted(User $student, Module $module): array
    {
        $levels = [];

        $completed = Course::query()
            ->where('user_id', $student->id)
            ->where('module_slug', $module->slug)
            ->whereNotNull('completed_at')
            ->orderBy('skill_level')
            ->get(['subtopic_slug', 'skill_level']);

        foreach ($completed as $course) {
            // Deduped: three attempts at 101, one completed twice, is still
            // "101" said once.
            $levels[$course->subtopic_slug][$course->skill_level->value] = $course->skill_level;
        }

        return array_map(array_values(...), $levels);
    }

    /**
     * What the student called each subtopic, newest naming wins.
     *
     * @return array<string, string>
     */
    private function titles(User $student, Module $module): array
    {
        return Course::query()
            ->where('user_id', $student->id)
            ->where('module_slug', $module->slug)
            ->orderBy('id')
            ->pluck('subtopic_title', 'subtopic_slug')
            ->all();
    }

    private function describe(SubtopicPointer $pointer): string
    {
        if ($pointer->levelsCompleted === []) {
            return 'no course completed yet';
        }

        return 'completed: '.implode(', ', array_map(
            fn (SkillLevel $level): string => $level->pathSegment(),
            $pointer->levelsCompleted,
        ));
    }
}
