<?php

namespace App\Lessons\Checkpoints;

use App\Ai\Gateway;
use App\Assessment\QuizKind;
use App\Assessment\Quizzes;
use App\Courses\Syllabus\SyllabusConcepts;
use App\Lessons\Exceptions\CheckpointUnusable;
use App\Lessons\LessonSections;
use App\Lessons\LessonTranscript;
use App\Models\Lesson;
use App\Models\Quiz;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writing the checkpoint that closes a section (spec 5.2.1).
 *
 * **Fails open**, which is the opposite of every other generator in the
 * system. 4.5.1's rule decides it: fail open only when failing closed would
 * destroy student progress. A syllabus that will not generate costs a retry;
 * a checkpoint that will not generate would strand a student mid-lesson with
 * a section they have already been taught and cannot leave. So the section
 * completes quizless, the incident is logged, and the lesson advances.
 *
 * One retry, then open, matching 4.5.1's shape -- and a plain re-issue rather
 * than a repair turn, for ledger D10.4's reasons: a repair turn breaks the
 * cached prefix and bills the invalid output back as input.
 */
final class CheckpointGenerator
{
    /** One retry (spec 5.2.1). */
    private const ATTEMPTS = 2;

    private const FEATURE = 'lesson.checkpoint';

    public function __construct(
        private readonly Gateway $gateway,
        private readonly Quizzes $quizzes,
    ) {}

    /**
     * The checkpoint for one taught section, generating it if needed.
     *
     * Pre: the section's teaching turn is in the transcript -- there is
     *      nothing to write questions about otherwise.
     * Post: either a `quizzes` row of kind `checkpoint` for this section
     *       exists and is returned, or null is returned and an incident is
     *       logged. In both cases the lesson may advance.
     *
     * Idempotent: a section that already has a checkpoint gets that one back
     * rather than a second generation, so reconnecting to a taught section
     * costs nothing and asks the same questions (5.2.6).
     */
    public function forSection(Lesson $lesson, int $sectionIndex): ?Quiz
    {
        $existing = $this->existing($lesson, $sectionIndex);

        if ($existing instanceof Quiz) {
            return $existing;
        }

        try {
            $checkpoint = $this->askUntilUsable($lesson, $sectionIndex);
        } catch (CheckpointUnusable $e) {
            // The one place in the engine that swallows a failure. The lesson
            // is worth more than the checkpoint (spec 5.2.1, 4.5.1).
            Log::warning('Checkpoint generation failed open; the section completes quizless.', [
                'lesson_id' => $lesson->id,
                'section_index' => $sectionIndex,
                'attempts' => self::ATTEMPTS,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->persist($lesson, $sectionIndex, $checkpoint);
    }

    /**
     * The checkpoint a section already has, if any.
     */
    public function existing(Lesson $lesson, int $sectionIndex): ?Quiz
    {
        return $this->quizzes->existing(QuizKind::Checkpoint, $lesson, $sectionIndex);
    }

    /**
     * @throws CheckpointUnusable
     */
    private function askUntilUsable(Lesson $lesson, int $sectionIndex): Checkpoint
    {
        $sections = LessonSections::of($lesson);
        $concepts = SyllabusConcepts::of($lesson->course->approvedSyllabus);
        $transcript = new LessonTranscript($lesson);

        $vars = [
            'section_transcript' => $transcript->renderSection($sectionIndex),
            'guidance_section' => $sections->guidance($sectionIndex),
        ];

        $failure = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                $response = $this->gateway
                    ->prompt('checkpoint.generate', $vars)
                    ->feature(self::FEATURE)
                    ->about($lesson)
                    ->schema(CheckpointSchema::definition())
                    ->generate();

                if (! is_array($response->structured)) {
                    throw new CheckpointUnusable('The model returned no structured checkpoint.');
                }

                return Checkpoint::fromModel($response->structured, $sectionIndex, $concepts);
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        throw CheckpointUnusable::after(self::ATTEMPTS, $failure);
    }

    /**
     * Store the checkpoint through 6.1's service.
     *
     * The create-or-adopt race two reconnecting clients can run lives there,
     * once, for all four kinds (ledger D12).
     */
    private function persist(Lesson $lesson, int $sectionIndex, Checkpoint $checkpoint): Quiz
    {
        return $this->quizzes->create(
            QuizKind::Checkpoint,
            $lesson,
            $lesson->course->student,
            $lesson->course,
            $checkpoint->payload(),
            $sectionIndex,
        );
    }
}
