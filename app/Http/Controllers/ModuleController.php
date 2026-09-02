<?php

namespace App\Http\Controllers;

use App\Courses\ModuleRollup;
use App\Courses\SkillLevel;
use App\Courses\SubtopicPointer;
use App\Models\Course;
use App\Models\Module;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Screen S12 -- the module rollup (spec 8.10.2, 3.3).
 *
 * `{module}/WORKING_KNOWLEDGE.md` is never written: it is rendered on demand
 * from the memory tree and the courses table, and `ModuleRollup` is the one
 * place that knows how (D8). This screen reads its pointers rather than
 * assembling a second answer to the same question -- which subtopics a student
 * has a knowledge record in, and which levels they have formally finished.
 *
 * What it adds is the courses behind each line, because the flow map's S12 has
 * an edge back to S1's card for a subtopic's course and a pointer carries no
 * course. The document a model reads and the screen a student reads differ in
 * exactly that: the model needs paths, the student needs the way back.
 *
 * **No policy.** A module is catalog, and every row below is drawn from this
 * student's own records under it -- the same reasoning as the module refresher
 * door (6.5). There is nothing here belonging to anyone else.
 *
 * Pre:  the student is signed in.
 * Post: a subtopic appears iff it has a knowledge record, so a student who
 *       withdrew still sees what the interview recorded.
 */
class ModuleController extends Controller
{
    public function __invoke(Request $request, Module $module, ModuleRollup $rollup): Response
    {
        $student = $request->user();

        $courses = Course::query()
            ->where('user_id', $student->id)
            ->where('module_slug', $module->slug)
            ->orderBy('skill_level')
            ->orderBy('attempt')
            ->get()
            ->groupBy('subtopic_slug');

        return Inertia::render('modules/show', [
            'module' => [
                'title' => $module->title,
            ],
            'subtopics' => array_map(
                fn (SubtopicPointer $pointer): array => $this->subtopic(
                    $pointer,
                    $courses->get($pointer->subtopicSlug) ?? new Collection,
                ),
                $rollup->pointers($student, $module),
            ),
            'urls' => [
                'dashboard' => route('dashboard'),
                'refresher' => route('modules.refreshers.store', $module),
            ],
        ]);
    }

    /**
     * One line of the rollup, and the courses that wrote it.
     *
     * @param  Collection<int, Course>  $courses
     * @return array<string, mixed>
     */
    private function subtopic(SubtopicPointer $pointer, Collection $courses): array
    {
        return [
            'title' => $pointer->title,
            'levels' => array_map(
                fn (SkillLevel $level): string => $level->title(),
                $pointer->levelsCompleted,
            ),
            'courses' => $courses
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'level' => $course->skill_level->title(),
                    'attempt' => $course->attempt,
                    'standing' => $course->standing()->title(),
                ])
                ->values()
                ->all(),
            // The subtopic grain hangs off a course (6.4), and `Refreshers`
            // anchors on the newest one in the subtopic whichever is handed
            // over. A subtopic with a knowledge record but no course at all
            // cannot be practised at this grain, and says nothing rather than
            // offering a button that 422s.
            'refresher_url' => $courses->last() instanceof Course
                ? route('courses.refreshers.store', $courses->last())
                : null,
        ];
    }
}
