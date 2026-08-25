<?php

namespace App\Console\Commands;

use App\Lessons\Pipeline\UnlockEscapeHatch;
use Illuminate\Console\Command;

/**
 * Spec 5.4.6's escape hatch, on a schedule.
 *
 * A parked chain would otherwise leave the course frozen with no student-
 * visible reason. This opens the next lesson anyway once the wait in
 * `config/lessons.php` is up: a stale working-knowledge document beats a
 * stuck course.
 */
class UnlockStalledLessonsCommand extends Command
{
    protected $signature = 'lessons:unlock-stalled';

    protected $description = 'Spec 5.4.6: open the next lesson for courses whose completion chain parked';

    public function handle(UnlockEscapeHatch $hatch): int
    {
        $unlocked = $hatch->sweep();

        if ($unlocked === []) {
            $this->components->info('No stalled lessons.');

            return self::SUCCESS;
        }

        foreach ($unlocked as $lesson) {
            $this->components->warn(sprintf(
                'Opened the lesson after [%d] (course %d) despite a parked pipeline.',
                $lesson->id,
                $lesson->course_id,
            ));
        }

        return self::SUCCESS;
    }
}
