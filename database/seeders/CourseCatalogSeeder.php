<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

/**
 * The preset catalog (spec 4.1).
 *
 * Seed data, not configuration: an admin UI edits these rows post-v1
 * (an explicit v1 WONT in PLAN.md), so until then changing the catalog means
 * re-running this seeder. Re-running is safe -- entries are matched by slug.
 *
 * A student is never limited to this list. Free text creates any subtopic
 * under any module; these are the starting points the picker offers.
 */
class CourseCatalogSeeder extends Seeder
{
    /**
     * @var array<string, array{title: string, subtopics: array<string, string>}>
     */
    private const CATALOG = [
        'math' => ['title' => 'Math', 'subtopics' => [
            'algebra' => 'Algebra',
            'geometry' => 'Geometry',
            'trigonometry' => 'Trigonometry',
            'calculus' => 'Calculus',
            'statistics' => 'Statistics',
            'linear-algebra' => 'Linear Algebra',
        ]],
        'science' => ['title' => 'Science', 'subtopics' => [
            'biology' => 'Biology',
            'chemistry' => 'Chemistry',
            'physics' => 'Physics',
            'astronomy' => 'Astronomy',
            'earth-science' => 'Earth Science',
        ]],
        'history' => ['title' => 'History', 'subtopics' => [
            'ancient-history' => 'Ancient History',
            'medieval-history' => 'Medieval History',
            'modern-history' => 'Modern History',
            'us-history' => 'US History',
            'history-of-science' => 'History of Science',
        ]],
        'language' => ['title' => 'Language', 'subtopics' => [
            'spanish' => 'Spanish',
            'french' => 'French',
            'german' => 'German',
            'italian' => 'Italian',
            'japanese' => 'Japanese',
            'mandarin' => 'Mandarin',
        ]],
        'econ' => ['title' => 'Econ', 'subtopics' => [
            'microeconomics' => 'Microeconomics',
            'macroeconomics' => 'Macroeconomics',
            'personal-finance' => 'Personal Finance',
            'game-theory' => 'Game Theory',
        ]],
        'philosophy' => ['title' => 'Philosophy', 'subtopics' => [
            'logic' => 'Logic',
            'ethics' => 'Ethics',
            'metaphysics' => 'Metaphysics',
            'epistemology' => 'Epistemology',
            'political-philosophy' => 'Political Philosophy',
        ]],
    ];

    public function run(): void
    {
        $modulePosition = 0;

        foreach (self::CATALOG as $slug => $module) {
            $record = Module::query()->updateOrCreate(
                ['slug' => $slug],
                ['title' => $module['title'], 'position' => $modulePosition++],
            );

            $position = 0;

            foreach ($module['subtopics'] as $subtopicSlug => $title) {
                $record->subtopics()->updateOrCreate(
                    ['slug' => $subtopicSlug],
                    ['title' => $title, 'position' => $position++],
                );
            }
        }
    }
}
