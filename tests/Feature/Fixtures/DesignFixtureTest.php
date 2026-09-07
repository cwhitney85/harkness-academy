<?php

namespace Tests\Feature\Fixtures;

use App\Assessment\Homework;
use App\Assessment\QuizDocument;
use App\Assessment\QuizKind;
use App\Assessment\QuizLength;
use App\Assessment\Refreshers;
use App\Courses\CourseStatus;
use App\Courses\ModuleRollup;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\LessonState;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\CourseCatalogSeeder;
use Database\Seeders\InterfaceFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Spec 8.2 -- the six states package 8 is designed against.
 *
 * One test per done-condition, asserted on what the fixture *is* rather than
 * on what a screen shows it as: none of the twelve screens exist yet, and the
 * point of building the fixture first is that they can be built against
 * something. Each test therefore checks the payload or the row the screen will
 * read, which is the half of the done-condition this session can settle.
 */
class DesignFixtureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.default', 'anthropic');
        config()->set('ai.providers.anthropic.key', 'test-key');

        $this->seed([CourseCatalogSeeder::class, InterfaceFixtureSeeder::class]);
    }

    /**
     * 8.2.1 -- one seeder, re-runnable, all six states, one known user.
     */
    #[Test]
    public function running_the_seeder_again_yields_the_same_six_states(): void
    {
        $before = $this->shape();

        $this->seed(InterfaceFixtureSeeder::class);

        $this->assertSame($before, $this->shape());

        // Four courses carrying six states, and every one of them the same
        // student's -- the memory tree, the rollup and the open-course rule
        // are all per-student.
        $this->assertSame(4, Course::query()->where('user_id', $this->student()->id)->count());
        $this->assertSame(4, Course::query()->count());
    }

    /**
     * 8.2.2 -- fresh course: an approved syllabus and nothing started.
     */
    #[Test]
    public function a_fresh_course_has_a_whole_syllabus_and_no_lesson_started(): void
    {
        $course = $this->course('travel-spanish');
        $syllabus = $course->approvedSyllabus->syllabus_json;

        $this->assertNotNull($course->approvedSyllabus->approved_at);
        $this->assertSame(CourseStatus::Active, $course->standing());

        $this->assertNotSame('', trim($syllabus['overview']));
        $this->assertCount(5, $syllabus['lessons']);
        $this->assertNotSame([], $syllabus['final_exam_concepts']);

        foreach ($syllabus['lessons'] as $lesson) {
            $this->assertNotSame('', trim($lesson['objective']), 'every lesson needs an objective to render');

            foreach ($lesson['sections'] as $section) {
                $this->assertNotSame([], $section['concepts'], 'every section needs its concept slugs');
            }
        }

        // Every exam slug is one a lesson section declares -- the coherence
        // the syllabus screen will show as a list and the exam relies on.
        SyllabusConcepts::of($course->approvedSyllabus)->assertDeclared($syllabus['final_exam_concepts']);

        $this->assertCount(5, $course->lessons);
        $this->assertSame(0, $course->lessons()->whereNotNull('started_at')->count());
    }

    /**
     * 8.2.3 -- a lesson stopped halfway, with a transcript to come back to.
     */
    #[Test]
    public function a_lesson_is_mid_flight_and_reopens_where_it_stopped(): void
    {
        $lesson = $this->lesson('conversational-spanish', 2);

        $this->assertGreaterThan(1, $lesson->section_index);
        $this->assertNull($lesson->completed_at);

        $state = LessonState::of($lesson)->toArray();

        $this->assertSame($lesson->section_index, $state['resume_at']);
        $this->assertSame(4, $state['lesson']['sections_total']);
        $this->assertNotSame([], $state['transcript']);

        // Taught sections restore; the ones beyond the resume point do not.
        $taught = array_column(array_filter($state['sections'], static fn (array $s): bool => $s['taught']), 'index');
        $this->assertSame([1, 2], $taught);

        // One section of genuinely long prose -- a column that only ever holds
        // three sentences tells you nothing about whether it holds at 44ch.
        $longest = max(array_map(
            static fn (array $turn): int => mb_strlen($turn['content']),
            array_filter($state['transcript'], static fn (array $turn): bool => $turn['kind'] === 'section'),
        ));

        $this->assertGreaterThan(1000, $longest);
    }

    /**
     * 8.2.4 -- the checkpoint that arrived and has not been answered.
     */
    #[Test]
    public function the_mid_flight_checkpoint_is_unanswered_and_withholds_its_key(): void
    {
        $lesson = $this->lesson('conversational-spanish', 2);

        $checkpoint = $lesson->quizzes()
            ->ofKind(QuizKind::Checkpoint)
            ->where('section_index', $lesson->section_index)
            ->firstOrFail();

        $this->assertSame(0, QuizAttempt::query()->where('quiz_id', $checkpoint->id)->count());

        // Stripped in the payload the client is handed (5.2.3) and intact in
        // the column it was stored from (5.2.2's lossless round-trip).
        $served = collect(LessonState::of($lesson)->toArray()['checkpoints'])
            ->firstWhere('checkpoint_id', $checkpoint->id);

        $encoded = json_encode($served, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('correct_option_id', $encoded);
        $this->assertStringNotContainsString('explanation', $encoded);

        foreach (QuizDocument::of($checkpoint)->questions() as $question) {
            $this->assertArrayHasKey('correct_option_id', $question);
        }
    }

    /**
     * 8.2.5 -- homework on a finished lesson that nobody has opened.
     */
    #[Test]
    public function a_completed_lesson_has_homework_nobody_has_answered(): void
    {
        $lesson = $this->lesson('conversational-spanish', 1);

        $this->assertNotNull($lesson->completed_at);

        $homework = $lesson->quizzes()->ofKind(QuizKind::Homework)->firstOrFail();

        $payload = Homework::of($homework, $this->student())->toArray();
        $encoded = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('correct_option_id', $encoded);
        $this->assertStringNotContainsString('explanation', $encoded);

        $this->assertNotSame([], $payload['questions']);

        foreach ($payload['progress'] as $row) {
            $this->assertFalse($row['answered'], 'the fixture homework must be ungraded');
        }
    }

    /**
     * 8.2.6, first half -- five lessons done, homework in, exam untouched.
     */
    #[Test]
    public function a_finished_course_stands_in_front_of_an_unsat_exam(): void
    {
        $course = $this->course('ordering-food-in-spanish');

        // Standing in front of it, not sitting at it. `final_exam` is the
        // status the student's own "Open the book" moves a course into (8.6),
        // so a fixture that arrived there had opened the book for them and
        // 8.9.2's closed state could never be drawn from it.
        $this->assertSame(CourseStatus::Active, $course->standing());
        $this->assertCount(5, $course->lessons);

        foreach ($course->lessons as $lesson) {
            $this->assertNotNull($lesson->completed_at, "lesson {$lesson->position} is not finished");

            $homework = $lesson->quizzes()->ofKind(QuizKind::Homework)->first();

            $this->assertInstanceOf(Quiz::class, $homework);
            $this->assertGreaterThan(0, QuizAttempt::query()->where('quiz_id', $homework->id)->count());
        }

        $exam = $course->morphMany(Quiz::class, 'quizzable')->ofKind(QuizKind::Final)->firstOrFail();
        $asked = count(QuizDocument::of($exam)->questions());

        // Two per lesson, the length a generated exam would have been given
        // (6.3.2) -- a hand-written fixture is still held to it.
        $this->assertSame(QuizLength::exam($course->lessons->count())->min, $asked);

        $this->assertSame(0, QuizAttempt::query()->where('quiz_id', $exam->id)->count(), 'the closed book has not been sat');
    }

    /**
     * 8.2.6, second half -- a fail, a re-sit of the same exam, and a pass.
     */
    #[Test]
    public function an_exam_was_failed_re_sat_and_passed(): void
    {
        $course = $this->course('spanish-pronunciation');

        $this->assertSame(CourseStatus::Completed, $course->standing());
        $this->assertNotNull($course->completed_at);

        $exams = $course->morphMany(Quiz::class, 'quizzable')->ofKind(QuizKind::Final)->get();

        $this->assertCount(1, $exams, 'a re-sit is the same exam (D20)');

        $exam = $exams->first();
        $total = count(QuizDocument::of($exam)->questions());
        $pass = QuizLength::exam($course->lessons->count())
            ->toPass($total, (float) config('assessment.final_exam.pass_mark'));

        $sittings = QuizAttempt::query()
            ->where('quiz_id', $exam->id)
            ->selectRaw('attempt, count(*) filter (where correct) as marked_right')
            ->groupBy('attempt')
            ->orderBy('attempt')
            ->pluck('marked_right', 'attempt')
            ->all();

        $this->assertCount(2, $sittings);
        $this->assertLessThan($pass, (int) $sittings[1], 'the first sitting has to fail for 8.9.3 to have a fail gate');
        $this->assertGreaterThanOrEqual($pass, (int) $sittings[2], 'and the second has to pass');
    }

    /**
     * 8.2.7 -- a completed subtopic and module, and all three grains generate.
     */
    #[Test]
    public function the_fixture_feeds_all_three_refresher_grains(): void
    {
        $student = $this->student();
        $course = $this->course('spanish-pronunciation');
        $lesson = $course->lessons()->where('position', 1)->firstOrFail();

        $module = Module::query()->where('slug', 'language')->firstOrFail();
        $rollup = app(ModuleRollup::class)->render($student, $module);

        $this->assertStringContainsString('Spanish Pronunciation (completed: 101)', $rollup);

        $refreshers = app(Refreshers::class);

        $this->scriptRefreshers($lesson->summary()->slugs());
        $this->assertSame(QuizKind::Refresher, $refreshers->forLesson($lesson)->kind);

        $this->scriptRefreshers($this->declaredIn($course));
        $this->assertSame(QuizKind::Refresher, $refreshers->forSubtopic($course)->kind);

        $this->scriptRefreshers($this->declaredAcrossTheModule($student, 'language'));
        $this->assertSame(QuizKind::Refresher, $refreshers->forModule($student, $module)->kind);
    }

    /**
     * 8.2.8 -- memory documents go through the path API, never past it.
     *
     * Modelled on MemoryDoorTest, which guards spec 3.2 over `app/`. The rule
     * is repo-wide; the seeder is simply the first thing outside `app/` that
     * writes memory at all.
     */
    #[Test]
    public function the_seeder_writes_memory_only_through_the_path_api(): void
    {
        $offenders = [];

        $pattern = '/App\\\\Models\\\\Memory(Version)?\b|\bMemory(Version)?::|[\'"]memor(ies|y_versions)[\'"]/';

        foreach (Finder::create()->files()->in(database_path('seeders'))->name('*.php') as $file) {
            if (preg_match($pattern, (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $offenders, 'the seeder must write memory through App\Memory (spec 3.2, 8.2.8): '.implode(', ', $offenders));

        // And it did write some: an empty tree would pass the check above for
        // the wrong reason.
        $paths = app(MemoryTree::class)->list($this->student());

        $this->assertContains('language/conversational-spanish/WORKING_KNOWLEDGE.md', $paths);
        $this->assertContains('language/conversational-spanish/101/COURSE_CRITERIA.md', $paths);
    }

    private function student(): User
    {
        return User::query()->where('email', InterfaceFixtureSeeder::EMAIL)->firstOrFail();
    }

    private function course(string $slug): Course
    {
        return Course::query()
            ->where('user_id', $this->student()->id)
            ->where('subtopic_slug', $slug)
            ->firstOrFail();
    }

    private function lesson(string $slug, int $position): Lesson
    {
        return $this->course($slug)->lessons()->where('position', $position)->firstOrFail();
    }

    /**
     * The fixture reduced to what a screen would notice, for comparing runs.
     *
     * @return list<array<string, mixed>>
     */
    private function shape(): array
    {
        $shape = [];

        foreach (Course::query()->orderBy('subtopic_slug')->get() as $course) {
            $shape[] = [
                'subtopic' => $course->subtopic_slug,
                'standing' => $course->standing()->value,
                'lessons' => $course->lessons->count(),
                'completed' => $course->lessons->filter->isCompleted()->count(),
                'sections_reached' => $course->lessons->pluck('section_index')->all(),
                'quizzes' => Quiz::query()->where('course_id', $course->id)->count(),
                'attempts' => QuizAttempt::query()
                    ->whereIn('quiz_id', Quiz::query()->where('course_id', $course->id)->select('id'))
                    ->count(),
            ];
        }

        return $shape;
    }

    /**
     * Every concept slug one course's syllabus declares.
     *
     * @return list<string>
     */
    private function declaredIn(Course $course): array
    {
        return SyllabusConcepts::of($course->approvedSyllabus)->declared();
    }

    /**
     * @return list<string>
     */
    private function declaredAcrossTheModule(User $student, string $moduleSlug): array
    {
        $slugs = [];

        $courses = Course::query()
            ->where('user_id', $student->id)
            ->where('module_slug', $moduleSlug)
            ->with('approvedSyllabus')
            ->get();

        foreach ($courses as $course) {
            foreach ($this->declaredIn($course) as $slug) {
                $slugs[$slug] = true;
            }
        }

        return array_keys($slugs);
    }

    /**
     * A refresher the model would have written, tagged inside the scope.
     *
     * The generation call is the one thing here that is not deterministic, so
     * it is faked -- 8.2.9 refuses to run the engines to *produce* the fixture,
     * and this is the fixture being read rather than written.
     *
     * @param  list<string>  $vocabulary
     */
    private function scriptRefreshers(array $vocabulary): void
    {
        $slugs = array_slice($vocabulary, 0, 3);

        $this->fakeModel(fn (string $prompt): array => [
            'quiz' => [
                'scope' => 'A warm-up on what you have already covered',
                'questions' => array_map(static fn (string $slug): array => [
                    'id' => 'r-'.$slug,
                    'prompt' => "A question about {$slug}, on an example you have not seen.",
                    'options' => [
                        ['id' => 'a', 'text' => 'The right answer'],
                        ['id' => 'b', 'text' => 'A plausible mistake'],
                        ['id' => 'c', 'text' => 'Another plausible mistake'],
                    ],
                    'correct_option_id' => 'a',
                    'explanation' => "Because of {$slug}.",
                    'concept_tag' => $slug,
                ], $slugs),
                'suggested_reading' => [],
            ],
        ]);
    }
}
