<?php

namespace Tests\Feature\Assessment;

use App\Assessment\Exceptions\ExamUnusable;
use App\Assessment\FinalExam;
use App\Assessment\QuizKind;
use App\Assessment\QuizLength;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Quiz;
use Database\Factories\CourseSyllabusFactory;
use PHPUnit\Framework\Attributes\Test;

/**
 * Spec 6.3.5 -- the exam, at the size a real course actually is.
 *
 * Every other fixture in the suite declares three exam concepts across two
 * lessons, and that is the only reason 6.3's coverage rule ever passed. The
 * first real course declared **44** exam concepts across 8 lessons against a
 * 12-question ceiling: a question carries exactly one tag, so the old rule --
 * every exam concept must be some question's tag -- could not be satisfied at
 * any price, and three presses of "Open the book" bought six `top`-tier calls
 * and no exam (2026-09-04).
 *
 * Spec 8.2.9 predicted this class of miss in writing: "the fixture is a
 * stand-in for real output; the first check against real output is the
 * dogfood cycle." This is that check, moved into the suite so the next one
 * fails here rather than in front of a student.
 */
class ExamAtCourseSizeTest extends AssessmentTestCase
{
    private const LESSONS = 8;

    private const SECTIONS = 2;

    private const CONCEPTS = 3;

    /**
     * Lesson titles a refusal can name, so the message reads as a student
     * would read it rather than as a position.
     *
     * @var array<int, string>
     */
    private const WORDS = [
        1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
        5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight',
    ];

    #[Test]
    public function an_exam_generates_for_a_course_of_eight_lessons_and_forty_concepts(): void
    {
        $course = $this->taughtCourse($this->largeCourse());
        $declared = $course->approvedSyllabus->syllabus_json;

        $this->assertCount(self::LESSONS, $declared['lessons']);
        $this->assertGreaterThanOrEqual(40, count($this->declaredConcepts()));

        $this->scriptModel([$this->examCovering(range(1, self::LESSONS))]);

        $exam = app(FinalExam::class)->open($course);

        $this->assertSame(QuizKind::Final, $exam->kind);
        $this->assertCount(QuizLength::exam(self::LESSONS)->min, $exam->payload['quiz']['questions']);

        // Sixteen questions against forty-eight concepts: all but eight of
        // them go unasked, and the exam is still a fair account of the course
        // (6.3.1). Under the old rule this was the failure.
        $unasked = array_diff($this->declaredConcepts(), array_column($exam->payload['quiz']['questions'], 'concept_tag'));

        $this->assertGreaterThanOrEqual(30, count($unasked));
        $this->assertSame(CourseStatus::FinalExam, $course->refresh()->status);
    }

    #[Test]
    public function an_exam_that_leaves_one_of_the_eight_lessons_unasked_is_refused(): void
    {
        $course = $this->taughtCourse($this->largeCourse());

        // Lesson 3 is never asked about. This is the rule 6.3.1 replaced the
        // per-concept one with, and deleting it makes this test fail.
        $skipping = $this->examCovering([1, 2, 4, 5, 6, 7, 8, 8]);

        $this->scriptModel([$skipping, $skipping]);

        try {
            app(FinalExam::class)->open($course);
            $this->fail('An exam that asks nothing from lesson 3 should be refused.');
        } catch (ExamUnusable $e) {
            $this->assertStringContainsString('lesson 3 (Lesson Three)', $e->getMessage());
        }

        $this->assertSame(0, Quiz::query()->ofKind(QuizKind::Final)->count());
        $this->assertSame(CourseStatus::Active, $course->refresh()->status);
    }

    /**
     * A course the size the depth parameters actually produce (spec 4.5.6).
     */
    private function largeCourse(): Course
    {
        $course = Course::factory()->status(CourseStatus::SyllabusDraft)->create();

        $tree = app(MemoryTree::class);
        $tree->put($course->student, $course->workingKnowledgePath(), self::WK);
        $tree->put($course->student, CourseCriteria::pathFor($course), self::CRITERIA);

        app(ApproveSyllabus::class)($course, CourseSyllabus::factory()->for($course)->create([
            'syllabus_json' => $this->largeSyllabus(),
        ]));

        return $course->refresh();
    }

    /**
     * Eight lessons, two sections each, three concepts a section.
     *
     * @return array<string, mixed>
     */
    private function largeSyllabus(): array
    {
        $syllabus = CourseSyllabusFactory::exampleSyllabus();
        $syllabus['lessons'] = [];

        foreach (range(1, self::LESSONS) as $position) {
            $sections = [];

            foreach (range(1, self::SECTIONS) as $section) {
                $sections[] = [
                    'title' => "Part {$section}",
                    'concepts' => $this->conceptsIn($position, $section),
                ];
            }

            $syllabus['lessons'][] = [
                'position' => $position,
                'title' => 'Lesson '.self::WORDS[$position],
                'objective' => "Work through lesson {$position}.",
                'sections' => $sections,
                'homework_focus' => "Practice lesson {$position}.",
                'estimated_minutes' => 20,
            ];
        }

        // Every concept the course teaches, named for the exam -- which is
        // what the first real course did, and what nothing could satisfy.
        $syllabus['final_exam_concepts'] = $this->declaredConcepts();

        return $syllabus;
    }

    /**
     * @return list<string>
     */
    private function conceptsIn(int $lesson, int $section): array
    {
        return array_map(
            static fn (int $index): string => "l{$lesson}-s{$section}-c{$index}",
            range(1, self::CONCEPTS),
        );
    }

    /**
     * @return list<string>
     */
    private function declaredConcepts(): array
    {
        $slugs = [];

        foreach (range(1, self::LESSONS) as $lesson) {
            foreach (range(1, self::SECTIONS) as $section) {
                $slugs = [...$slugs, ...$this->conceptsIn($lesson, $section)];
            }
        }

        return $slugs;
    }

    /**
     * A `json/quiz.v1` exam of the right length, tagged one concept per
     * question -- each question asking about the lesson at that place.
     *
     * @param  list<int>  $lessons  the lesson each pair of questions is about
     * @return array<string, mixed>
     */
    private function examCovering(array $lessons): array
    {
        $questions = [];

        foreach ($lessons as $index => $lesson) {
            foreach ([1, 2] as $of) {
                $slug = $this->conceptsIn($lesson, $of)[0];
                $id = 'x'.($index * 2 + $of);

                $questions[] = [
                    'id' => $id,
                    'prompt' => "Question {$id}, on {$slug}, in a situation the course did not walk through.",
                    'options' => [
                        ['id' => 'a', 'text' => 'The right answer'],
                        ['id' => 'b', 'text' => 'A plausible mistake'],
                        ['id' => 'c', 'text' => 'Another plausible mistake'],
                    ],
                    'correct_option_id' => 'a',
                    'explanation' => "Because of {$slug}.",
                    'concept_tag' => $slug,
                ];
            }
        }

        return [
            'quiz' => [
                'scope' => 'The whole course',
                'questions' => $questions,
                'suggested_reading' => [],
            ],
        ];
    }
}
