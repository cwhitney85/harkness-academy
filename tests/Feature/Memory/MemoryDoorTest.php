<?php

namespace Tests\Feature\Memory;

use PHPUnit\Framework\Attributes\Test;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Spec 3.2 -- the path API is the *only* door to memory.
 *
 * A guard rather than a behaviour test: the rule is only worth stating if
 * something fails when a future call site reaches past it.
 */
class MemoryDoorTest extends TestCase
{
    /**
     * The files allowed to name the memory tables or their models.
     *
     * @var list<string>
     */
    private const DOOR = [
        'Memory/MemoryTree.php',
        'Models/Memory.php',
        'Models/MemoryVersion.php',
    ];

    private const REACHES_PAST_THE_DOOR = '/App\\\\Models\\\\Memory(Version)?\b|\bMemory(Version)?::|[\'"]memor(ies|y_versions)[\'"]/';

    #[Test]
    public function no_class_outside_the_path_api_touches_the_memory_tables(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
            $relative = $this->relativePath($file);

            if (in_array($relative, self::DOOR, true)) {
                continue;
            }

            if (preg_match(self::REACHES_PAST_THE_DOOR, (string) file_get_contents($file->getPathname())) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, 'These files reach past the path API (spec 3.2): '.implode(', ', $offenders));
    }

    /**
     * Spec 16.3.4 -- an admin reads memory, and nothing else: whatever holds a
     * `MemoryTree` in an admin controller calls `history` on it and no other
     * method.
     */
    #[Test]
    public function an_admin_controller_only_reads_memory(): void
    {
        $offenders = [];

        foreach (Finder::create()->files()->in(app_path('Http/Controllers/Admin'))->name('*.php') as $file) {
            $source = (string) file_get_contents($file->getPathname());

            // A tree resolved inline, or a variable or property typed as one.
            preg_match_all('/MemoryTree \$(\w+)/', $source, $holders);
            $receivers = ['MemoryTree::class\)', ...array_map(fn (string $name): string => '\$(?:this->)?'.$name, $holders[1])];

            preg_match_all('/(?:'.implode('|', $receivers).')->(\w+)\(/', $source, $calls);

            foreach (array_diff($calls[1], ['history']) as $method) {
                $offenders[] = $this->relativePath($file)." calls {$method}";
            }
        }

        $this->assertSame([], $offenders, 'Admin reads memory through MemoryTree::history only (spec 16.3.4): '.implode(', ', $offenders));
    }

    private function relativePath(SplFileInfo $file): string
    {
        return str_replace(app_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
