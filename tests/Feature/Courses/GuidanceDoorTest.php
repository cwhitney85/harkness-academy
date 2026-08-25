<?php

namespace Tests\Feature\Courses;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Spec 4.5.3 -- GUIDANCE.md is model-facing only.
 *
 * Exactly one consumer may read it: the lesson engine (5.1). The student's
 * syllabus view renders from the approved syllabus JSON, so the two views of a
 * course cannot drift apart. That is only worth stating if something fails
 * when a future screen reaches for the teaching notes -- which is this test.
 *
 * Modelled on MemoryDoorTest, which guards spec 3.2 the same way.
 */
class GuidanceDoorTest extends TestCase
{
    /**
     * Where a route, controller or component would live.
     *
     * @var list<string>
     */
    private const USER_FACING = ['routes', 'app/Http', 'resources/js', 'resources/views'];

    private const READS_GUIDANCE = '/GUIDANCE\.md|\bLessonGuidance\b|\bGuidanceDocument\b|guidance_path/';

    #[Test]
    public function no_route_or_component_reads_lesson_guidance(): void
    {
        $offenders = [];

        foreach (self::USER_FACING as $directory) {
            $path = base_path($directory);

            if (! is_dir($path)) {
                continue;
            }

            $files = Finder::create()->files()->in($path)
                ->name(['*.php', '*.ts', '*.tsx', '*.js', '*.jsx', '*.blade.php']);

            foreach ($files as $file) {
                if (preg_match(self::READS_GUIDANCE, (string) file_get_contents($file->getPathname())) === 1) {
                    $offenders[] = $directory.'/'.$file->getRelativePathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'GUIDANCE.md is model-facing only (spec 4.5.3); these read it: '.implode(', ', $offenders));
    }
}
