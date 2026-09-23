<?php

namespace App\Courses;

use App\Ai\Gateway;
use App\Courses\Exceptions\SubtopicBelongsElsewhere;
use App\Courses\Exceptions\SubtopicCheckUnavailable;
use App\Courses\Exceptions\SubtopicOutOfScope;
use App\Models\Module;
use App\Models\Subtopic;
use App\Models\SubtopicRefusal;
use App\Models\User;
use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Does this typed subtopic belong under the module the student picked?
 * (spec 4.9, ledger D42)
 *
 * "algebra" typed under Language used to start a Language course about
 * algebra and carry it through both interviews, the working-knowledge
 * document and a `top`-tier syllabus. 4.5.7's `subtopic_mismatch` never
 * looked: that flag is *goals against subtopic*, raised after all of that has
 * been paid for. Mismatch is a flag at syllabus time and a blocker at
 * creation time, because at creation time a refusal costs a sentence.
 *
 * Pre:  `$name` came from free text -- the catalog path calls no model, and a
 *       catalog subtopic is in scope by construction.
 * Post: the name belongs under `$module`, or nothing was written and one of
 *       three refusals was thrown. A refused name leaves a `subtopic_refusals`
 *       row (4.9.7); an unanswerable check leaves none, because nothing was
 *       judged.
 *
 * **The catalog is not an ownership table, and the model has to be told so
 * twice** (D42). The prompt says placement is not exclusive, and on a verbatim
 * slug collision `collision` says the match does not settle the question.
 * Without the second, `logic` under Math is refused because Philosophy lists
 * it. A collision is detectable mechanically and not resolvable mechanically,
 * which is why it is a hint to the model rather than a verdict of its own.
 */
final class SubtopicScopeCheck
{
    /**
     * One retry, per spec 4.9.5 -- a plain re-issue of the same call, as
     * 4.5.1 has it.
     */
    private const ATTEMPTS = 2;

    /**
     * Seconds per attempt (4.9.5). Well under the `cheap` tier's own budget:
     * a student is waiting on the submit button, and a check measured at
     * ~1.6s that has gone quiet for fifteen seconds is not going to answer.
     */
    private const TIMEOUT = 15;

    private const FEATURE = 'subtopic.check';

    public function __construct(private readonly Gateway $gateway) {}

    /**
     * @throws SubtopicBelongsElsewhere|SubtopicOutOfScope|SubtopicCheckUnavailable
     */
    public function check(User $student, Module $module, SubtopicName $name): void
    {
        // 16.2.7 -- a removed subtopic's name is refused before anything is
        // asked. Under this module that name *is* the removed row, and a
        // course started on it would collide with the row, or with its
        // restore (D57). The student is told only that it is not taught.
        if (Subtopic::onlyTrashed()->where('module_id', $module->id)->where('slug', $name->slug)->exists()) {
            throw new SubtopicOutOfScope($name, $module);
        }

        // 4.9.1 -- the gate spends nothing it need not. A slug that matches a
        // starting point under this very module is the catalog's own entry
        // arrived at by typing (`SubtopicName` makes them the same course), so
        // there is nothing to ask. A starting point is a listed row (16.1):
        // an unlisted student row passed once, for someone else, and is asked
        // about again.
        if ($this->isPresetOf($module, $name)) {
            return;
        }

        $modules = $this->modules();
        $answer = $this->askUntilAnswered($student, $module, $name, $modules);

        if ($answer['verdict'] === 'in_scope') {
            return;
        }

        $suggested = $answer['verdict'] === 'wrong_module' ? $modules[$answer['module']] : null;

        SubtopicRefusal::query()->create([
            'user_id' => $student->id,
            'typed_name' => $name->title,
            'module_slug' => $module->slug,
            'verdict' => $answer['verdict'],
            'suggested_module_slug' => $suggested?->slug,
            'reason' => $answer['reason'],
        ]);

        throw $suggested instanceof Module
            ? new SubtopicBelongsElsewhere($name, $module, $suggested)
            : new SubtopicOutOfScope($name, $module);
    }

    /**
     * One retry, then fail closed (4.9.5).
     *
     * An answer that does not satisfy 4.9.3's contract is a failed attempt
     * rather than a verdict: an unseeded module slug or a `wrong_module` with
     * nowhere to send the student cannot be turned into copy.
     *
     * @param  array<string, Module>  $modules
     * @return array{verdict: string, module: string|null, reason: string}
     *
     * @throws SubtopicCheckUnavailable
     */
    private function askUntilAnswered(User $student, Module $module, SubtopicName $name, array $modules): array
    {
        $failure = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            try {
                return $this->ask($student, $module, $name, $modules);
            } catch (Throwable $e) {
                $failure = $e;
            }
        }

        // The student is told only that we could not check just now. Why we
        // could not is written down here or nowhere.
        Log::error('The subtopic scope check gave up; no course was started.', [
            'user_id' => $student->id,
            'module_slug' => $module->slug,
            'subtopic_slug' => $name->slug,
            'attempts' => self::ATTEMPTS,
            // Reaching here means every attempt threw, so there is always a
            // reason to give.
            'reason' => $failure->getMessage(),
        ]);

        throw new SubtopicCheckUnavailable($name, self::ATTEMPTS);
    }

    /**
     * @param  array<string, Module>  $modules
     * @return array{verdict: string, module: string|null, reason: string}
     */
    private function ask(User $student, Module $module, SubtopicName $name, array $modules): array
    {
        $response = $this->gateway
            ->prompt('subtopic.check', [
                'catalog' => $this->catalog($modules),
                'module' => $module->title,
                'name' => $name->title,
                'collision' => $this->collision($module, $name),
            ])
            ->feature(self::FEATURE)
            ->for($student)
            ->schema(self::definition(array_keys($modules)))
            ->timeout(self::TIMEOUT)
            ->generate();

        return $this->verdict($response->structured, $module, $modules);
    }

    /**
     * 4.9.3's contract, read back.
     *
     * A reply that does not satisfy it throws, which the retry loop above
     * treats exactly as a provider failure -- an unusable answer and no
     * answer are the same thing to a student waiting on a submit button.
     *
     * @param  array<string, Module>  $modules
     * @return array{verdict: string, module: string|null, reason: string}
     */
    private function verdict(mixed $structured, Module $module, array $modules): array
    {
        if (! is_array($structured)) {
            throw new RuntimeException('The model returned no structured verdict.');
        }

        $verdict = $structured['verdict'] ?? null;
        $named = $structured['module'] ?? null;
        $reason = $structured['reason'] ?? null;

        if (! in_array($verdict, ['in_scope', 'wrong_module', 'off_platform'], true)) {
            throw new RuntimeException('The model returned no usable verdict.');
        }

        if (! is_string($reason) || trim($reason) === '') {
            throw new RuntimeException("Verdict [{$verdict}] came with no reason.");
        }

        if ($verdict !== 'wrong_module') {
            return ['verdict' => $verdict, 'module' => null, 'reason' => $reason];
        }

        // A redirect has to name somewhere to send the student, and it cannot
        // be where they already are.
        if (! is_string($named) || ! isset($modules[$named]) || $named === $module->slug) {
            throw new RuntimeException('A wrong_module verdict named no other module we teach.');
        }

        return ['verdict' => $verdict, 'module' => $named, 'reason' => $reason];
    }

    /**
     * `json/subtopic-scope.v1` (spec 4.9.3).
     *
     * Defined here rather than in a class of its own: unlike `json/quiz.v1` it
     * has one consumer and always will.
     *
     * @param  list<string>  $moduleSlugs
     * @return Closure(JsonSchema): array<string, Type>
     */
    private static function definition(array $moduleSlugs): Closure
    {
        return fn (JsonSchema $schema): array => [
            'verdict' => $schema->string()->enum(['in_scope', 'wrong_module', 'off_platform'])->required()
                ->description('Whether a course on this topic belongs under the subject the student picked.'),

            'module' => $schema->string()->enum($moduleSlugs)->nullable()->required()
                ->description('Null unless the verdict is wrong_module; then the slug of the subject it belongs under.'),

            'reason' => $schema->string()->min(1)->required()
                ->description('One sentence saying why. Never shown to the student.'),
        ];
    }

    private function isPresetOf(Module $module, SubtopicName $name): bool
    {
        return Subtopic::query()
            ->listed()
            ->where('module_id', $module->id)
            ->where('slug', $name->slug)
            ->exists();
    }

    /**
     * The collision sentence, or nothing (spec 4.9.2).
     *
     * Rendered only on a verbatim slug match under another module, and it says
     * outright that the match does not settle the question -- the wording is
     * measured, and D42 has the numbers.
     */
    private function collision(Module $module, SubtopicName $name): string
    {
        $elsewhere = Subtopic::query()
            ->listed()
            ->with('module')
            ->where('slug', $name->slug)
            ->where('module_id', '!=', $module->id)
            ->first();

        if (! $elsewhere instanceof Subtopic) {
            return '';
        }

        return "\n\n«{$name->title}» is also one of the starting points listed under {$elsewhere->module->title}. "
            .'That does not settle the question — many topics are genuinely taught under more than one subject. '
            ."Judge whether it is also at home under {$module->title}.";
    }

    /**
     * The catalog as the model reads it (spec 4.9.2).
     *
     * From the listed `subtopics` rows rather than from the prompt file,
     * because promotion (16.1.5) adds to them and a prompt that listed them
     * would go quietly stale. Unlisted student rows are not the catalog.
     *
     * @param  array<string, Module>  $modules
     */
    private function catalog(array $modules): string
    {
        $lines = [];

        foreach ($modules as $module) {
            $titles = $module->subtopics->pluck('title')->all();

            $lines[] = "- **{$module->title}** (`{$module->slug}`) — ".implode(', ', $titles);
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, Module>
     */
    private function modules(): array
    {
        return Module::query()
            ->with(['subtopics' => fn ($subtopics) => $subtopics->listed()])
            ->orderBy('position')
            ->get()
            ->keyBy('slug')
            ->all();
    }
}
