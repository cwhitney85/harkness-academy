<?php

namespace Tests\Unit\Ai;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Spec 14.1.4 -- no model ID in code.
 *
 * The tier indirection is only real if nothing can quietly route around it, so
 * this walks the source tree. Models live in the `ai_models` catalog; the
 * migration that seeds it and the recorded provider responses under
 * tests/fixtures are the only files that may name one. This file is exempt
 * because it has to name the patterns it hunts for.
 */
class NoModelIdsInCodeTest extends TestCase
{
    private const SEEDING_MIGRATION = 'database/migrations/2026_09_19_000000_create_ai_catalog_tables.php';

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
    public function no_model_id_appears_in_code(): void
    {
        $allowed = [
            base_path(self::SEEDING_MIGRATION),
            __FILE__,
        ];

        $offenders = [];

        $files = Finder::create()
            ->files()
            ->in([base_path('app'), base_path('config'), base_path('database'), base_path('routes'), base_path('prompts')])
            ->in([base_path('resources/js'), base_path('tests')])
            ->name(['*.php', '*.ts', '*.tsx', '*.md'])
            ->exclude('fixtures')
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
            ['Model IDs belong in the ai_models catalog, not in code (spec 14.1.4). Found:'],
            $offenders,
        )));
    }

    #[Test]
    public function the_seeding_migration_is_where_the_model_ids_actually_live(): void
    {
        $this->assertMatchesRegularExpression(
            $this->patterns()[0],
            (string) file_get_contents(base_path(self::SEEDING_MIGRATION)),
            'This test can only prove an absence if its patterns still match a real model ID.',
        );
    }

    #[Test]
    public function config_ai_no_longer_holds_the_tiers(): void
    {
        $this->assertNull(config('ai.tiers'));
    }
}
