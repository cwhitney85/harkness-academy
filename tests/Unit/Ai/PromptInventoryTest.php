<?php

namespace Tests\Unit\Ai;

use App\Ai\Prompts\PromptFiles;
use App\Ai\Prompts\PromptOutput;
use App\Ai\Tier;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Spec 2.3.2 -- "inventory <-> codebase audit passes".
 *
 * The inventory table in docs/specs/02-ai-layer.md is the spec; `prompts/` is
 * the code. This test reads the table and holds the tree to it, so the two
 * cannot drift: a prompt file with no row fails, a row with no file fails, and
 * a tier or variable list that disagrees fails.
 */
class PromptInventoryTest extends TestCase
{
    #[Test]
    public function every_inventory_row_has_a_prompt_file_and_vice_versa(): void
    {
        $inventory = $this->inventory();
        $prompts = $this->repository()->all();

        $this->assertNotEmpty($inventory, 'The 2.3.2 inventory table could not be read from the spec.');

        $this->assertSame(
            array_keys($inventory),
            array_keys($prompts),
            'Every prompt file needs a row in the 2.3.2 inventory, and every row needs a file.',
        );
    }

    #[Test]
    public function every_prompt_declares_the_tier_the_inventory_gives_it(): void
    {
        $prompts = $this->repository()->all();

        foreach ($this->inventory() as $key => $row) {
            $this->assertArrayHasKey($key, $prompts);

            $this->assertSame(
                $row['tier'],
                $prompts[$key]->tier->value,
                "Prompt [{$key}] does not run at the tier the 2.3.2 inventory assigns it.",
            );
        }
    }

    #[Test]
    public function every_prompt_declares_the_inputs_the_inventory_gives_it(): void
    {
        $prompts = $this->repository()->all();

        foreach ($this->inventory() as $key => $row) {
            $expected = $row['inputs'];
            $actual = $prompts[$key]->vars;

            sort($expected);
            sort($actual);

            $this->assertSame($expected, $actual, "Prompt [{$key}] does not declare the inputs the 2.3.2 inventory gives it.");
        }
    }

    #[Test]
    public function every_structured_prompt_names_the_schema_the_inventory_names(): void
    {
        $prompts = $this->repository()->all();

        foreach ($this->inventory() as $key => $row) {
            if ($row['schema'] === null) {
                continue;
            }

            $this->assertSame(PromptOutput::Json, $prompts[$key]->output, "Prompt [{$key}] should declare a json output.");
            $this->assertSame($row['schema'], $prompts[$key]->schema, "Prompt [{$key}] names the wrong schema.");
        }
    }

    #[Test]
    public function every_callable_prompt_runs_at_a_tier_that_resolves_to_a_model(): void
    {
        foreach ($this->repository()->all() as $key => $prompt) {
            if (! $prompt->output->isCallable()) {
                continue;
            }

            $tier = $prompt->tier === Tier::Session ? Tier::Mid : $prompt->tier;

            $this->assertTrue(
                $tier->isBillable(),
                "Prompt [{$key}] is sent to a provider but its tier resolves to no model.",
            );
        }
    }

    private function repository(): PromptFiles
    {
        return new PromptFiles(base_path('prompts'));
    }

    /**
     * Read the 2.3.2 inventory table out of the spec.
     *
     * @return array<string, array{tier: string, inputs: list<string>, schema: ?string}>
     */
    private function inventory(): array
    {
        $spec = (string) file_get_contents(base_path('docs/specs/02-ai-layer.md'));

        $rows = [];

        foreach (explode("\n", $spec) as $line) {
            if (preg_match('/^\|\s*`(?<key>[a-z0-9._]+)`\s*\|(?<rest>.*)\|\s*$/i', trim($line), $m) !== 1) {
                continue;
            }

            $cells = array_map(trim(...), explode('|', $m['rest']));

            if (count($cells) < 4) {
                continue;
            }

            [$tier, $inputs, $output] = $cells;

            $rows[$m['key']] = [
                'tier' => $this->normalizeTier($tier),
                'inputs' => $this->normalizeInputs($inputs),
                'schema' => preg_match('/`json\/(?<schema>[a-z0-9.\-]+)`/i', $output, $s) === 1 ? $s['schema'] : null,
            ];
        }

        ksort($rows);

        return $rows;
    }

    private function normalizeTier(string $cell): string
    {
        return match (trim($cell)) {
            '—', '-', '' => Tier::None->value,
            '(session)' => Tier::Session->value,
            default => trim($cell),
        };
    }

    /**
     * @return list<string>
     */
    private function normalizeInputs(string $cell): array
    {
        $inputs = array_filter(array_map(
            fn (string $input): string => trim($input, " \t`"),
            explode(',', $cell),
        ));

        return array_values($inputs);
    }
}
