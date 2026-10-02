<?php

namespace Tests\Feature\Courses;

use App\Ai\Prompts\PromptFiles;
use App\Ai\Tier;
use App\Courses\Syllabus\SyllabusSchema;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 4.5.2.1 -- the prompts and the schema have to name the same contract.
 *
 * The gateway checks that a prompt declaring a json output was handed *a*
 * schema (spec 2.3.1), but nothing makes it the right one. If a prompt file's
 * frontmatter drifted to `json/syllabus.v2` the calls would still succeed and
 * quietly validate against v1, so the agreement is pinned here.
 */
class SyllabusContractTest extends TestCase
{
    private const PROMPTS = ['syllabus.generate', 'syllabus.revise'];

    #[Test]
    public function both_syllabus_prompts_declare_the_schema_this_package_owns(): void
    {
        foreach (self::PROMPTS as $key) {
            $this->assertSame(
                SyllabusSchema::CONTRACT,
                $this->prompts()->get($key)->outputContract(),
                "Prompt [{$key}] no longer matches SyllabusSchema.",
            );
        }
    }

    #[Test]
    public function both_syllabus_prompts_run_at_the_top_tier(): void
    {
        foreach (self::PROMPTS as $key) {
            $this->assertSame(Tier::Top, $this->prompts()->get($key)->tier, $key);
        }
    }

    #[Test]
    public function the_schema_requires_every_field_the_spec_names(): void
    {
        $fields = array_keys(SyllabusSchema::definition()(new JsonSchemaTypeFactory));

        $this->assertSame([
            'course_title',
            'overview',
            'lessons',
            'final_exam_concepts',
            'subtopic_mismatch',
        ], $fields);
    }

    private function prompts(): PromptFiles
    {
        return app(PromptFiles::class);
    }
}
