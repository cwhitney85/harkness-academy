<?php

namespace Database\Factories;

use App\Courses\CourseCriteria;
use App\Memory\MemoryTree;
use App\Models\Course;
use App\Models\CourseSyllabus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseSyllabus>
 */
class CourseSyllabusFactory extends Factory
{
    protected $model = CourseSyllabus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'generation' => 1,
            'syllabus_json' => self::exampleSyllabus(),
            'feedback' => null,
            'wk_version_id' => fn (array $attributes) => $this->pin($attributes, 'wk'),
            'criteria_version_id' => fn (array $attributes) => $this->pin($attributes, 'criteria'),
            'approved_at' => null,
        ];
    }

    public function generation(int $generation): self
    {
        return $this->state(fn (): array => [
            'generation' => $generation,
            'feedback' => $generation > 1 ? 'Less theory, more worked examples.' : null,
        ]);
    }

    public function approved(): self
    {
        return $this->state(['approved_at' => now()]);
    }

    public function mismatched(string $note = 'The stated goals are about cooking, not algebra.'): self
    {
        return $this->state(fn (array $attributes): array => [
            'syllabus_json' => [...$attributes['syllabus_json'], 'subtopic_mismatch' => $note],
        ]);
    }

    /**
     * A `json/syllabus.v1` document that satisfies the schema (spec 4.5.2).
     *
     * Every slug in `final_exam_concepts` is declared by a lesson section --
     * the self-consistency the generation path enforces.
     *
     * @return array<string, mixed>
     */
    public static function exampleSyllabus(): array
    {
        return [
            'course_title' => 'Algebra: Getting Comfortable with the Unknown',
            'overview' => 'You already read graphs confidently, so this course starts at the point where letters stand in for numbers and builds to solving for them.',
            'lessons' => [
                [
                    'position' => 1,
                    'title' => 'Letters as Numbers',
                    'objective' => 'Read and write expressions where a letter stands for a quantity you do not know yet.',
                    'sections' => [
                        ['title' => 'What a variable is', 'concepts' => ['variable']],
                        ['title' => 'Building expressions', 'concepts' => ['expression', 'coefficient']],
                    ],
                    'homework_focus' => 'Translating short word problems into expressions.',
                    'estimated_minutes' => 20,
                ],
                [
                    'position' => 2,
                    'title' => 'Keeping Both Sides Equal',
                    'objective' => 'Solve a one-step linear equation and check the answer.',
                    'sections' => [
                        ['title' => 'Equations as balances', 'concepts' => ['equation', 'variable']],
                        ['title' => 'Undoing an operation', 'concepts' => ['inverse-operation']],
                    ],
                    'homework_focus' => 'One-step equations with addition and multiplication.',
                    'estimated_minutes' => 20,
                ],
            ],
            'final_exam_concepts' => ['variable', 'equation', 'inverse-operation'],
            'subtopic_mismatch' => null,
        ];
    }

    /**
     * The memory version a generation was built from.
     *
     * Written through the path API rather than by inserting rows, so a fixture
     * cannot pin a version the tree does not actually hold (spec 3.2).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function pin(array $attributes, string $which): int
    {
        $course = Course::query()->whereKey($attributes['course_id'])->firstOrFail();
        $tree = app(MemoryTree::class);

        $path = $which === 'wk'
            ? $course->workingKnowledgePath()
            : CourseCriteria::pathFor($course);

        if ($tree->get($course->student, $path) === null) {
            $tree->put($course, $path, "# {$which} fixture");
        }

        return $tree->document($course->student, $path)->versionId;
    }
}
