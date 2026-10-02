<?php

namespace Database\Seeders;

use App\Assessment\FinalExam;
use App\Assessment\QuizGrader;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Courses\CourseCriteria;
use App\Courses\CourseStatus;
use App\Courses\CourseTransitions;
use App\Courses\Syllabus\ApproveSyllabus;
use App\Lessons\LessonProgress;
use App\Lessons\LessonSections;
use App\Lessons\LessonSummary;
use App\Lessons\LessonTranscript;
use App\Lessons\Speaker;
use App\Lessons\Streaming\TurnKind;
use App\Lessons\SummaryDocument;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\Subtopic;
use App\Models\User;
use Database\Seeders\Fixtures\ConversationalSpanish;
use Database\Seeders\Fixtures\CourseFixture;
use Database\Seeders\Fixtures\LessonFixture;
use Database\Seeders\Fixtures\OrderingFoodInSpanish;
use Database\Seeders\Fixtures\SpanishPronunciation;
use Database\Seeders\Fixtures\TravelSpanish;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/**
 * The six states package 8 designs against (spec 8.2).
 *
 * Built before anything is styled, because no course has ever been generated:
 * without this, every screen in package 8 would be laid out against an empty
 * database. A seeder rather than a script that runs the engines (8.2.9) -- it
 * is deterministic, it costs nothing, and it can be re-run at the start of
 * every session.
 *
 * **It writes rows, but not blindly.** Memory documents go through the path
 * API because that is a standing convention and 8.2.8 restates it; syllabus
 * approval, lesson progress, quizzes, grading and course transitions go
 * through the classes that own those columns, because a fixture that reached
 * around them would drift from what the engines actually produce and the
 * screens would be built against a shape that never occurs. What the seeder
 * supplies is the *content* those writers would have got from the model.
 *
 * The one place it does write directly is `lessons.summary_json`, which has no
 * writer outside the completion chain (5.4.2) and no way to be set without
 * running it.
 *
 * The six states, and where each lives:
 *
 * | State | Course |
 * |---|---|
 * | 8.2.2 fresh course | Travel Spanish |
 * | 8.2.3 lesson mid-flight | Conversational Spanish, lesson 2 |
 * | 8.2.4 unanswered checkpoint | Conversational Spanish, lesson 2 section 2 |
 * | 8.2.5 ungraded homework | Conversational Spanish, lesson 1 |
 * | 8.2.6 course complete, exam unsat | Ordering Food in Spanish |
 * | 8.2.7 completed subtopic and module | Spanish Pronunciation |
 */
class InterfaceFixtureSeeder extends Seeder
{
    /**
     * The known user of 8.2.1. Everything below hangs off this one student,
     * because the memory tree, the rollup and the open-course rule are all
     * per-student and a fixture spread over several would exercise none of
     * them.
     */
    public const EMAIL = 'colin@harkness.test';

    public const PASSWORD = 'password';

    public function __construct(
        private readonly MemoryTree $memory,
        private readonly CourseTransitions $transitions,
        private readonly ApproveSyllabus $approve,
        private readonly LessonProgress $progress,
        private readonly Quizzes $quizzes,
        private readonly QuizGrader $grader,
        private readonly FinalExam $finalExam,
    ) {}

    public function run(): void
    {
        $student = $this->student();

        // Re-runnable (8.2.1). Courses cascade to their syllabi, lessons,
        // transcripts, quizzes and attempts, so clearing them is enough to
        // rebuild the same six states rather than a second set beside them.
        //
        // Memory documents are deliberately *not* cleared: the path API has no
        // delete, and re-putting the same content simply adds a version. The
        // tree ends up saying exactly what it said before.
        //
        // A hard delete: this resets fixtures, it does not remove a student's
        // course, so it is not a removal (16.2) and leaves no mark.
        Course::query()->where('user_id', $student->id)->forceDelete();

        foreach ($this->fixtures() as $fixture) {
            $this->build($student, $fixture);
        }

        $this->command->info('Design fixture seeded for '.self::EMAIL.' (password: '.self::PASSWORD.').');
    }

    /**
     * @return list<CourseFixture>
     */
    private function fixtures(): array
    {
        return [
            ConversationalSpanish::fixture(),
            TravelSpanish::fixture(),
            OrderingFoodInSpanish::fixture(),
            SpanishPronunciation::fixture(),
        ];
    }

    private function student(): User
    {
        return User::query()->firstOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Colin Whitney',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'tos_accepted_at' => now(),
            ],
        );
    }

    /**
     * One course, walked as far as its fixture goes.
     */
    private function build(User $student, CourseFixture $fixture): void
    {
        $course = Course::query()->create([
            'user_id' => $student->id,
            'subtopic_id' => Subtopic::findOrCreateFor(
                Module::query()->where('slug', $fixture->moduleSlug)->sole(),
                $fixture->slug,
                $fixture->title,
            )->id,
            'module_slug' => $fixture->moduleSlug,
            'subtopic_slug' => $fixture->slug,
            'subtopic_title' => $fixture->title,
            'skill_level' => $fixture->level,
            'attempt' => 1,
            'status' => CourseStatus::Created,
        ]);

        $this->writeDocuments($course, $fixture);

        // The two interviews wrote the documents above; the pipeline position
        // has to walk the same arrows a real course walked (D7).
        foreach ([CourseStatus::KnowledgeInterview, CourseStatus::GoalsInterview, CourseStatus::SyllabusDraft] as $status) {
            $course = $this->transitions->to($course, $status);
        }

        ($this->approve)($course, $this->draft($course, $fixture));

        $course->refresh();

        foreach ($fixture->lessons as $lesson) {
            $this->buildLesson($course, $lesson);
        }

        if ($fixture->exam !== null) {
            $this->buildExam($course->refresh(), $fixture);
        }
    }

    /**
     * The two documents the interviews would have written (spec 4.3, 4.4).
     *
     * Through the path API and nowhere near the tables underneath it (8.2.8).
     */
    private function writeDocuments(Course $course, CourseFixture $fixture): void
    {
        $this->memory->put($course, $course->workingKnowledgePath(), $fixture->workingKnowledge);
        $this->memory->put($course, CourseCriteria::pathFor($course), $fixture->criteria);
    }

    /**
     * Generation 1, pinned to the documents it was built from (4.5.4.1).
     *
     * The pins are read back out of the tree rather than invented, so the row
     * cannot name a version whose content is something else (D10.1).
     */
    private function draft(Course $course, CourseFixture $fixture): CourseSyllabus
    {
        return CourseSyllabus::query()->create([
            'course_id' => $course->id,
            'generation' => 1,
            'syllabus_json' => $fixture->syllabus,
            'feedback' => null,
            'wk_version_id' => $this->pin($course, $course->workingKnowledgePath()),
            'criteria_version_id' => $this->pin($course, CourseCriteria::pathFor($course)),
        ]);
    }

    /**
     * The version of a document this course was built from.
     *
     * Absence is a bug in the seeder rather than a case: `writeDocuments` runs
     * first, and a syllabus row cannot exist without both pins.
     */
    private function pin(Course $course, string $path): int
    {
        $document = $this->memory->document($course->student, $path);

        if ($document === null) {
            throw new LogicException("The fixture wrote no document at [{$path}].");
        }

        return $document->versionId;
    }

    /**
     * Teach a lesson as far as the fixture says the student got.
     */
    private function buildLesson(Course $course, LessonFixture $fixture): void
    {
        /** @var Lesson $lesson */
        $lesson = $course->lessons()->where('position', $fixture->position)->firstOrFail();

        $transcript = new LessonTranscript($lesson);

        foreach ($fixture->taught as $offset => $section) {
            $index = $offset + 1;

            $this->progress->enterSection($lesson, $index);
            $transcript->append(TurnKind::Section, Speaker::Tutor, $section->prose, $index, (string) Str::uuid());

            foreach ($section->exchange as [$speaker, $content]) {
                $transcript->append(TurnKind::Qa, $speaker, $content, $index, (string) Str::uuid());
            }
        }

        foreach ($fixture->checkpoints as $checkpoint) {
            $quiz = $this->quizzes->create(
                QuizKind::Checkpoint,
                $lesson,
                $course->student,
                $course,
                $checkpoint->payload(),
                $checkpoint->sectionIndex,
            );

            $this->answer($quiz, $course, $checkpoint->answers);
        }

        if ($fixture->completed) {
            $this->finish($lesson->refresh(), $fixture);
        }

        if ($fixture->homework !== null) {
            $quiz = $this->quizzes->create(QuizKind::Homework, $lesson, $course->student, $course, $fixture->homework);

            $this->answer($quiz, $course, $fixture->homeworkAnswers);
        }
    }

    /**
     * Hand the lesson in, and leave behind what the chain would have (5.4).
     *
     * `summary_json` is written directly because nothing but the completion
     * chain writes it, and the chain is a model call. Everything downstream of
     * it -- SUMMARY.md, the next lesson unlocking -- goes through the classes
     * that own those effects, so the rendered document and the JSON cannot
     * disagree (5.4.3).
     */
    private function finish(Lesson $lesson, LessonFixture $fixture): void
    {
        $this->progress->complete($lesson, LessonSections::of($lesson), new LessonTranscript($lesson));

        $lesson->refresh();

        if ($fixture->summary !== null) {
            $lesson->forceFill(['summary_json' => $fixture->summary])->save();

            $this->memory->put(
                $lesson->course,
                SummaryDocument::pathFor($lesson),
                SummaryDocument::render($lesson, LessonSummary::of($fixture->summary)),
            );
        }

        $this->progress->unlockNext($lesson);
    }

    /**
     * The exam, and any sittings the student has had at it (spec 6.3).
     *
     * The quiz is written first and `open()` then adopts it, which is the same
     * idempotent path a re-sit takes -- so no generation happens and no model
     * is called (8.2.9).
     */
    private function buildExam(Course $course, CourseFixture $fixture): void
    {
        $this->quizzes->create(QuizKind::Final, $course, $course->student, $course, $fixture->exam ?? []);

        // A course with no sitting behind it is left with the book **shut**
        // (8.2.6: "both the closed book and a re-sit render"). `open()` is
        // what moves a course to `final_exam`, and a fixture that called it
        // unconditionally left no state in which 8.9.2's closed book could be
        // drawn -- and no way for a seeded student to open one, since opening
        // an exam that already exists adopts it rather than generating.
        if ($fixture->sittings === []) {
            return;
        }

        $this->finalExam->open($course);

        foreach ($fixture->sittings as $offset => $answers) {
            $result = $this->finalExam->sit($course->refresh(), $answers);

            // A failed sitting hands the course back at `active` (D7b). Seat
            // them again only if the fixture says they came back.
            if (! $result->passed && isset($fixture->sittings[$offset + 1])) {
                $this->finalExam->open($course->refresh());
            }
        }
    }

    /**
     * @param  array<string, string>  $answers  question id => option id
     */
    private function answer(Quiz $quiz, Course $course, array $answers): void
    {
        foreach ($answers as $questionId => $optionId) {
            $this->grader->grade($quiz, $course->student, $questionId, $optionId);
        }
    }
}
