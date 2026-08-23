<?php

namespace Tests\Unit\Ai;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Spec 2.1 -- "grep finds zero model IDs outside config".
 *
 * The tier indirection is only real if nothing can quietly route around it, so
 * this walks the source tree the way the done-condition says to. config/ai.php
 * is the one sanctioned home; this file is exempt because it has to name the
 * patterns it hunts for.
 */
class NoModelIdsOutsideConfigTest extends TestCase
{
    /**
     * Model ID shapes, assembled at runtime so this file does not trip itself.
     *
     * @return list<string>
     */
    private function patterns(): array
    {
        return [
            '/\b'.'claude'.'-[a-z0-9][a-z0-9.\-]*/i',
            '/\b'.'gpt'.'-[0-9][a-z0-9.\-]*/i',
            '/\b'.'gemini'.'-[0-9][a-z0-9.\-]*/i',
        ];
    }

    #[Test]
    public function no_model_id_appears_outside_config_ai(): void
    {
        $allowed = [
            base_path('config/ai.php'),
            __FILE__,
        ];

        $offenders = [];

        $files = Finder::create()
            ->files()
            ->in([base_path('app'), base_path('config'), base_path('database'), base_path('routes'), base_path('prompts')])
            ->in([base_path('resources/js'), base_path('tests')])
            ->name(['*.php', '*.ts', '*.tsx', '*.md'])
            ->filter(fn ($file): bool => ! in_array($file->getPathname(), $allowed, true));

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            foreach ($this->patterns() as $pattern) {
                if (preg_match($pattern, $contents, $matches) === 1) {
                    $offenders[] = sprintf(
                        '%s names [%s]',
                        str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()),
                        $matches[0],
                    );
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['Model IDs may only appear in config/ai.php (spec 2.1). Found:'],
            $offenders,
        )));
    }

    #[Test]
    public function config_ai_is_where_the_model_ids_actually_live(): void
    {
        $config = (string) file_get_contents(base_path('config/ai.php'));

        $this->assertMatchesRegularExpression(
            $this->patterns()[0],
            $config,
            'This test can only prove an absence if config/ai.php still names models.',
        );
    }
}
