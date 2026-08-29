<?php

namespace Tests\Feature\Assessment;

use App\Assessment\Exceptions\RefresherNotAvailable;
use App\Assessment\QuizKind;
use App\Assessment\Refreshers;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Module;
use App\Models\User;
use Database\Factories\CourseSyllabusFactory;
use Database\Seeders\CourseCatalogSeeder;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.5 -- "2-subtopic module quiz draws from both WKs".
 *
 * The rollup is derived and never written (3.3), so a module-grain quiz cannot
 * read `{module}/WORKING_KNOWLEDGE.md` -- there is no such document. It walks
 * the rollup's pointers instead and reads the subtopic documents they name,
 * which is the whole reason 3.3 exposes `pointers()` alongside `render()`.
 */
class ModuleRefresherTest extends AssessmentTestCase
{
    private const GEOMETRY_WK = "## Solid\n\n- Names the parts of a triangle without hesitating.\n\n## Shaky\n\n- Which angles a theorem actually licenses.";

    #[Test]
    public function a_module_quiz_draws_on_every_subtopic_the_rollup_points_at(): void
    {
        $algebra = $this->activeCourse();
        $geometry = $this->secondSubtopic($algebra->student);

        $this->scriptModel([$this->refresherResponse(['variable', 'theorem'])]);

        $quiz = app(Refreshers::class)->forModule($algebra->student, $this->module());

        $prompt = $this->sent[0];

        // Both subtopic records, each named by its own path.
        $this->assertStringContainsString('### `'.$algebra->workingKnowledgePath().'`', $prompt);
        $this->assertStringContainsString('### `'.$geometry->workingKnowledgePath().'`', $prompt);
        $this->assertStringContainsString('Reads graphs confidently', $prompt);
        $this->assertStringContainsString('Names the parts of a triangle', $prompt);

        // And never the rollup itself, which is not a document.
        $this->assertNotContains('math/WORKING_KNOWLEDGE.md', app(MemoryTree::class)->list($algebra->student));

        $this->assertStringContainsString('the module "Math" (Algebra, Geometry)', $prompt);

        $this->assertSame(QuizKind::Refresher, $quiz->kind);
        $this->assertSame($this->module()->getMorphClass(), $quiz->quizzable_type);
        $this->assertSame($this->module()->id, $quiz->quizzable_id);
    }

    #[Test]
    public function a_module_quiz_belongs_to_no_single_course(): void
    {
        $algebra = $this->activeCourse();
        $this->secondSubtopic($algebra->student);

        $this->scriptModel([$this->refresherResponse(['variable'])]);

        $quiz = app(Refreshers::class)->forModule($algebra->student, $this->module());

        // Null means "more than one course", not "unknown": its vocabulary
        // spans both subtopics, so naming one course would be untrue.
        $this->assertNull($quiz->course_id);
        $this->assertSame($algebra->user_id, $quiz->user_id);
    }

    #[Test]
    public function its_vocabulary_spans_the_subtopics(): void
    {
        $algebra = $this->activeCourse();
        $this->secondSubtopic($algebra->student);

        // `theorem` is declared only by the geometry course; `variable` only by
        // the algebra one. A module quiz may ask about either.
        $this->scriptModel([$this->refresherResponse(['theorem'])]);

        $quiz = app(Refreshers::class)->forModule($algebra->student, $this->module());

        $this->assertSame('theorem', $quiz->payload['quiz']['questions'][0]['concept_tag']);
    }

    #[Test]
    public function a_module_the_student_has_never_studied_has_nothing_to_refresh(): void
    {
        $student = User::factory()->create();

        $this->expectException(RefresherNotAvailable::class);

        app(Refreshers::class)->forModule($student, $this->module());
    }

    #[Test]
    public function the_route_builds_the_quiz_for_whoever_is_signed_in(): void
    {
        $algebra = $this->activeCourse();
        $this->secondSubtopic($algebra->student);

        $this->scriptModel([$this->refresherResponse(['variable'])]);

        $created = $this->actingAs($algebra->student)
            ->postJson("/modules/{$this->module()->id}/refreshers")
            ->assertCreated()
            ->json();

        foreach ($created['questions'] as $question) {
            $this->assertArrayNotHasKey('correct_option_id', $question);
        }

        // A stranger asking gets their own (empty) records, not this
        // student's -- so the answer is 422, never someone else's quiz.
        $this->actingAs(User::factory()->create())
            ->postJson("/modules/{$this->module()->id}/refreshers")
            ->assertStatus(422);
    }

    /**
     * A second approved course in the same module, in a different subtopic.
     */
    private function secondSubtopic(User $student): Course
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create([
            'user_id' => $student->id,
            'subtopic_slug' => 'geometry',
            'subtopic_title' => 'Geometry',
        ]);

        $tree = app(MemoryTree::class);
        $tree->put($student, $course->workingKnowledgePath(), self::GEOMETRY_WK);
        $tree->put($student, CourseCriteria::pathFor($course), self::CRITERIA);

        // The same shape of syllabus with its own vocabulary, so a slug can
        // prove which subtopic it came from.
        $syllabus = json_decode(str_replace(
            'variable',
            'theorem',
            (string) json_encode(CourseSyllabusFactory::exampleSyllabus()),
        ), true);

        app(ApproveSyllabus::class)(
            $course,
            CourseSyllabus::factory()->for($course)->create(['syllabus_json' => $syllabus]),
        );

        return $course->refresh();
    }

    private function module(): Module
    {
        if (Module::query()->doesntExist()) {
            $this->seed(CourseCatalogSeeder::class);
        }

        return Module::query()->where('slug', 'math')->sole();
    }
}
