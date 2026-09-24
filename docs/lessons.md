# Harkness Academy — traps and postmortems

*Durable tier (ledger [D27](02-design.md)). Required by spec 9.5. What this file owns: **tooling traps** — behaviour of Laravel, Vite, the AI SDK or the host that cost a session and will cost the next one — and **incident postmortems**, where something reached a student or a bill before it was found.*

**What does not belong here.** A rule goes in the spec that sets it, a decision goes in the ledger, a runbook step goes in [`docs/deploy.md`](deploy.md), and what a session tried before it found the answer goes in that unit's plan file and is deleted with it. An entry here earns its place by being a thing you can walk into again.

---

## T1. `withoutVite()` in `TestCase::setUp` — the stale-manifest trap

*Found 2026-09-01, in the 8.8 session.*

An Inertia screen test renders `app.blade.php`, which resolves `@vite` against `public/build/manifest.json`. So **every screen test 500s the moment that manifest is older than the page under test** — which it is for the whole of any session that adds a screen, until someone runs a build.

The error names a Vite manifest and looks nothing like the stale build it actually is. Two sessions can lose an hour to it before the penny drops.

**The fix is standing:** `tests/TestCase.php`'s `setUp` calls `withoutVite()`. No test in the suite renders a built asset, so nothing is lost. Verified with `public/build` deleted outright.

CI was never affected — `composer setup` builds before `composer ci:check` — which is exactly why this stayed a local-only trap for as long as it did.

## T2. `fakeModel()`, not `GatewayAgent::fake()`

*Found 2026-08-25, in the 4.5 session.*

`laravel/ai` keys its fakes on the **exact agent class**. Faking `GatewayAgent` alone therefore leaves `StructuredGatewayAgent` unfaked, so **structured calls reach the live API from the test suite** — a real bill, real latency, and a green test that proves nothing about the fixture it claims to use.

**Use `TestCase::fakeModel()`** for anything with a `json/` output. It fakes both classes.

## T3. `install:features` stripped package 1's auth scaffolding

*2026-08-23. Resolved; recorded because the shape recurs.*

Installing `laravel/ai` fired the starter kit's one-shot `install:features` command through Composer's `post-update-cmd`. Running non-interactively it took defaults, stripped package 1's passkey and 2FA scaffolding, and then — by its own design — deleted itself, so the cause was gone before the damage was noticed.

**Not a standing hazard: it cannot fire again.** The command and its `chisel.php` self-cleanup block were deleted 2026-08-23.

`chisel.php`, `chisel-paths.php`, the `laravel/chisel` dependency and the `/* @chisel-* */` markers in eight package-1 files are unreachable now — nothing requires them. Left in place; removing them is package-1 tidying, not a fix.

**The general shape, which is the reason this entry exists:** a starter kit's `post-update-cmd` runs on every `composer require`. Read what a starter kit installs into that hook before adding a dependency.

## T4. Production had no queue worker and no API key

*2026-09-02, first deploy that met a student. Ledger [D25](02-design.md); the runbook that now prevents it is [`docs/deploy.md`](deploy.md) steps 6 and 8.*

Two omissions, one deploy, two very different symptoms.

**No `ANTHROPIC_API_KEY`.** Nothing injects it, so the app deployed clean and died at the first interview.

**No queue worker.** The runbook said to add no background process *"until there is work for them"* — a sentence written before package 5, which has dispatched `ShouldQueue` jobs since. An environment with no worker **completes lessons and silently never writes their homework**: no error, no failed job, no student-visible symptom until the homework screen waits forever.

The lesson is the second one. A "not needed yet" note in a runbook has no expiry date and nobody rereads it; by the time anyone did, the work had existed for two packages. **A runbook step that is conditional on the future is a defect.** `docs/deploy.md` now states the queue as required and names what breaks without it.

## T5. The final exam could not be sat, and never could have

*Found 2026-09-04 on the first real course; repaired 2026-09-06 as spec items 6.3.1–6.3.5 and 8.9.5. Decisions in ledger [D26](02-design.md) and [D34](02-design.md).*

**What happened.** The first real course reached its final exam and could not sit it. Pressing "Open the book" dispatched `WriteFinalExam`, which spent about 90 seconds and $0.16–0.24 per attempt, twice per press, and refused the exam both times. The refusal was in the logs:

> `` `exam.final` did not produce a usable exam in 2 attempts: It asks nothing about [medieval-period-boundaries, early-middle-ages, … 44 slugs] ``

**Why it could never have worked.** The coverage rule was **arithmetically unsatisfiable**. `FinalExam::uncovered()` refused any exam unless every slug in the syllabus's `final_exam_concepts` was some question's `concept_tag`. A question carries **exactly one** tag, so coverage can never exceed the question count — and course 1 declared 44 exam concepts against a 12-question ceiling. No model output could have passed, at any price, for any course whose syllabus declared more exam concepts than the exam had questions.

**It shipped because every test fixture declares three exam concepts.** With three required and twelve allowed, the rule passes easily and looks correct. Spec 8.2.9 had predicted exactly this in writing — *the fixture is a stand-in for a course, and a stand-in that is smaller than the real thing hides the rules that only bind at real size.* The repair's 6.3.5 is a regression guard at course size for that reason: 8 lessons, 40+ declared concepts.

**A second, independent defect hid the first.** `WriteFinalExam` did record a student-facing message on the `GenerationRequest` row, and `FinalExamController` did pass it to the screen as a `failure` prop — but `final-exam.tsx` copied that prop into local state at mount, and the five-second poll leaves the component mounted. So the message never reached the student: the button simply re-enabled, in silence, and the only symptom of a $0.20 failure was nothing happening. That is the first known instance of the mounted-component class, now ledger [D28](02-design.md).

**Three lessons worth carrying.**

1. **A rule whose satisfiability depends on the ratio of two independent numbers needs a test at realistic values of both.** Every fixture in the suite sat on the safe side of the ratio.
2. **A failure surface that is never exercised is not a failure surface.** The message existed, was written, was passed to the screen, and was invisible.
3. **Cost makes a silent failure expensive.** Two `top`-tier attempts per press, and a student presses again because nothing told them not to.

## T6. Laravel Dusk cannot be installed — it pins guzzle 7

*Found 2026-09-08, in the mounted-component unit.*

`composer require --dev laravel/dusk` does not resolve. Every release through **v8.6.0 requires `guzzlehttp/guzzle ^7.5`**, and this project is on **guzzle 8.0.2** — which is what `laravel/framework` and `aws/aws-sdk-php` both prefer (`^7.8.2 || ^8.0`). Composer's message names carbon and PHP versions from Dusk v1–v6 and buries the one constraint that actually matters.

With `-W` it *does* resolve, by **downgrading guzzle to 7.15.5** and dragging ~20 other packages with it, `laravel/framework` included. Downgrading the HTTP client the AI gateway streams over, to add a test runner, is not a trade to make in passing.

**So client-side behaviour is proven by `vitest` + `jsdom` + `@testing-library/react`** (`npm test`, wired into `composer ci:check`). No browser, no served app, no fake gateway in another process. Revisit Dusk if it ever accepts guzzle 8 — and note that it would then also need the AI faked *inside* `php artisan serve`, since `GatewayAgent::fake()` swaps a container singleton and cannot reach another process.

One jsdom gap to know when writing such a test: `use-mobile` calls `matchMedia` at **module scope**, so a stub installed in `beforeAll` is already too late. Hoist it — `vi.hoisted()` runs before the imports.

And one vitest gap: `vi.mock('@inertiajs/react', ...)` replaces the **whole** module, so an export the screen reaches for and the factory omits throws at module scope and fails every test in the file. `8.5.7` put a `<Link>` in the shell's crest and broke `interview.test.tsx` that way, on a screen the crest is not the subject of. A component added to a mocked module's surface is a change to every mock of it.

## T7. The model writes `**bold**`, and the student read the asterisks

*Found 2026-09-08 on the first real course, repaired as spec item 8.8.7.*

`lesson.section` invites `*emphasis*` and nothing else, so `lesson-section.tsx` read exactly that: `split(/\*([^*\n]+)\*/g)`, one span, no markdown dependency. Real output does not honour the invitation. Course 1's section 2 carried `**absolute payoff**`, `**relative payoff**` and `**cross-scenario-subtraction-error**` alongside the `*emphasis*` the prompt asked for, and against a body that requires a non-`*` character the pairing lands one asterisk late: the student read `an *absolute payoff` and then eleven lines of unintended italics, closing on `same*`.

**Three things generalise from it.**

1. **A prompt is a request, not a grammar.** Anything that parses model output has to survive syntax the prompt never invited — here by pairing *runs* of asterisks rather than matching one exact shape, so `*this*` and `**this**` are both simply the one emphasis the design has.
2. **A renderer that cuts text into strings and parses each piece separately breaks every span that crosses a cut.** The explainer's mark cut the paragraph in two and asked each half for its own pair; the fix reads the markup first and lays the mark over the runs, which is also what makes the mark findable — the student selected text with the asterisks already gone.
3. **Streaming is a partial-input problem.** A paragraph mid-arrival always holds an unclosed opener. Treating it as emphasis that has not finished yet is right and needs no special case; printing it is a stray asterisk on every section the tutor teaches.

It was visible on screen for as long as the course had run. Nobody looked at the prose with an eye for *this*, and no test rendered anything but well-formed input.

## T8. A literal `\u2014` on screen was three hops upstream of the renderer

*Found 2026-09-08 on the first real course, repaired as spec item 4.8 ([D39](02-design.md)).*

The symptom was an em dash printed as `\u2014` on a lesson screen, and the obvious suspect was the thing that had just been fixed for [T7](#t7-the-model-writes-bold-and-the-student-read-the-asterisks) — the prose renderer. It was innocent. `Prose` passes text through unchanged, and **no `lesson_messages` row has ever contained the sequence**; the string was not lesson prose at all but `lesson.objective`, printed by the lesson completion screen out of a syllabus generated six days earlier.

**Grep the data before reading the renderer.** One walk of every text column in the development database found the eight affected fields, the one `course_syllabi` row they belonged to, and the three clean rows that proved it was a single bad generation rather than a code path — in less time than reading one component took. A rendering defect and a storage defect look identical on screen and are not adjacent in the code.

**The same generation also wrote `\n\n` for a paragraph break, which nobody had noticed.** Whatever made the model escape its output twice did it to every string in the document, so the search is for the class of defect and not for the character that was reported.

Model-facing documents carry it too: `GUIDANCE.md` renders from the same JSON, so the tutor had been reading the escapes back as its own context for six days. Repairing stored prose means re-rendering everything derived from it.

## T9. A bare `.svg` in headless Chrome renders 8px off

*Found 2026-09-11, in the `harkness` rename.*

Package 7's four icons are rasterised from the two SVG sources in `resources/icons/` by headless Chrome. Loading the `.svg` file **directly** — `--screenshot file://…/icon.svg` — renders it as a document, and Chrome gives a document an 8px body margin. Every pixel shifts down and right by 8, the field no longer reaches the edge, and a maskable icon's safe circle is no longer centred.

Nothing in the output says so. The PNG is the right dimensions and looks approximately right at a glance; only overlaying it on the previous one shows the offset.

**The fix is `scripts/icons.sh`**, which wraps the SVG in a `margin:0` page and sets the `<img>` to the exact target size. Run it after any change to the crest, and commit the PNGs with the SVGs.

A second trap sits beside it: `resources/icons/icon.svg` has `width="512" height="512"` on both the `<svg>` element and its background `<rect>`. A `sed` that rescales the document by matching that string rescales the background too, and the field shrinks to a square in the corner.

## T10. The lesson stream was cut at 20 seconds by nginx, not by the student

*Found 2026-09-12, on the first real course run against `harkness.academy`.*

A lesson's first section streamed nothing, showed "The tutor could not be reached", and did it again on reload. The interviews before it had streamed fine, and the syllabus had generated fine. The logs named it exactly:

```
[error] upstream timed out (110: Connection timed out) while reading upstream,
request: "GET /lessons/19/sections/1/stream", upstream: "fastcgi://127.0.0.1:9000"
```

**Laravel Cloud's nginx gives PHP-FPM 20 seconds to produce the next byte**, and the wait for Anthropic's first token is longer than that on a lesson-sized prompt. Every paired FPM trace shows PHP parked in `fread()` inside `laravel/ai`'s `ParsesServerSentEvents::readLine()` — blocked on the provider's socket, unable to write anything of its own.

**It is not SSE buffering**, which is the hazard [R4](02-design.md) names and [deploy.md](deploy.md) carried as unverified for four packages. Buffering would have broken the interviews too. Streaming works on Cloud; what fails is silence.

**Three ceilings, and they were in the wrong order.** PHP's `max_execution_time` is 0 and FPM sets no `request_terminate_timeout`, so the only limits are nginx's 20s and the application's own 120s — `lesson.system` is `tier: mid`, and `lesson.section` inherits it. A proxy that gives up six times sooner than the application means the application's error handling never runs, which is the same defect the queue's `--timeout` had at 60s under a 300s cap.

**The setting is "HTTP timeout" in the environment's General Settings, and it caps at 60 seconds.** It renders `fastcgi_read_timeout` through the container's `NGINX_HTTP_TIMEOUT`. A custom environment variable of that name does nothing — custom variables reach the application's `.env`, not the container environment nginx's entrypoint reads — which cost an hour and two deploys to establish. **60 is below the `mid` tier's 120**, so the ordering the queue gets right cannot be bought here: the rule becomes *nothing inside a streamed request may go quiet for 60 seconds*, and deploy.md owns it.

**The heartbeat could not have saved it, and that is the second trap.** `EventStream::heartbeatIfDue()` beats at 15 seconds, comfortably under 20 — but it is only ever called from inside the delta callback, so it cannot beat until the stream it is protecting has already started. Anthropic does send its own keepalive during a long wait, and `laravel/ai`'s `HandlesTextStreaming` parses the `ping` event and then matches no branch for it, so it is dropped before `each()` ever sees it. **There is no seam in the SDK that fires during the wait**, which is why the ordering of ceilings is what this depends on rather than a heartbeat.

## T11. The phone keyboard corrects an exact-match field, and the screen cannot say so

*Found 2026-09-11, in the `harkness` unit, on spec 1.6.*

An invitation code is matched **case-sensitively and exactly**. iOS capitalises the first letter of a plain `<input type="text">` by default, and offers autocorrect on it — so a correct code typed on a phone arrives wrong, on the one screen where the app is forbidden to say what the right answer looks like (1.6.4). The visitor sees *That is not the invitation code* and has nothing to check it against.

**The fix is three attributes on any field the app compares exactly:** `autoCapitalize="none" autoCorrect="off" spellCheck={false}`. Codes, slugs and identifiers all qualify; a name or a free-text answer does not.

Nothing in a desktop browser reproduces it, and no test catches it — the field's value in a test is whatever the test typed.

## T12. Boost puts `infer-conventions` back on every update, and its defaults write `CLAUDE.md`

*Found 2026-09-16, installing `laravel/boost` 2.9.*

`infer-conventions` sweeps the codebase and records what it finds as **project rules** in `.ai/rules/` — a second home for facts the ledger, the specs and this file already own. Project rules are off (`BOOST_RULES_ENABLED=false`, which also removes the `record-rule` MCP tool), but **the skill is installed regardless of that setting**. Deleting `.claude/skills/infer-conventions/` and its entry in `boost.json` does not hold: the next `php artisan boost:update` restores both. So it stays on disk, and the standing convention in `CLAUDE.md` is what keeps it from being run.

**`boost.json` is what keeps `CLAUDE.md` intact.** With no `boost.json`, `boost:install` defaults to every feature — guidelines included, which writes Boost's guideline block into `CLAUDE.md` — and, run with `--no-interaction`, to every coding agent it detects on the machine. Pass `--skills --mcp` explicitly, and don't delete `boost.json`.

## T13. A browser check runs in Colin's own Chrome, session and all

*Found 2026-09-17, building spec 12.*

The Chrome that browser automation drives is Colin's, and it keeps whatever it was last signed in as. The demo's welcome page redirected any signed-in demo account past itself. That browser was still signed in to a guest from an earlier build, so the check landed on course creation — and was reported as the browser's state rather than as the bug. Colin then hit the same redirect and never saw the page.

**When a page's behaviour depends on who is signed in, a redirect in the check is a finding, not noise.** Either check the signed-in branch on purpose, or say plainly that the signed-out page was not seen. A page a stranger is meant to see should not have a signed-in branch that skips it.

## T14. `migrate:fresh` drops tables, not functions

*Found 2026-09-18, building spec 13.*

`RefreshDatabase` rebuilds the test database with `migrate:fresh`, which drops every table and view — and leaves Postgres functions where they are. The append-only trigger on `admin_actions` needs a function, and a plain `CREATE FUNCTION` passed on the first test run and failed every run after it, on every test in the suite.

**A migration that creates a function says `CREATE OR REPLACE FUNCTION`.** The same holds for anything else `migrate:fresh` leaves behind: types, extensions, sequences made by hand.

## T15. A user set on the guard outlives the request, in tests

*Found 2026-09-18, building spec 13.4.*

Viewing as a student sets the student as the request's user, leaving the session signed in as the admin. In production that lasts one request, because the next one reads its user from the session again. The test client reuses one application, so the auth guard kept the student, and the next request in the test arrived as the student. Every view-as check after the first then read as a mismatch. Octane keeps the application alive between requests in the same way.

**Middleware that swaps the user puts the original back in a `finally`.** Context is the exception: a streamed response does its work after the middleware returns, and what it reads from Context must still be there.

## T16. The cache will not hand back an object

*Found 2026-09-19, building spec 14.1.*

`config/cache.php` sets `serializable_classes` to `false`, so the cache refuses to unserialize any object. An Eloquent model or collection put in the cache is written without complaint and comes back broken on the next read. With the array store the tests use, the value never gets serialized, so the tests pass anyway.

**Cache plain arrays.** `App\Ai\Catalog` is the example: it maps its rows to arrays before it caches them.

**When a reader needs models, rebuild them on the way out: `AiModel::hydrate($rows)`.** That gives real models with their casts, no query, and a cache that still holds only plain data. Don't turn `serializable_classes` on instead: it loosens the gadget-chain guard for the whole app, and a cached object breaks when a deploy changes its class. `(object) $row` is no substitute — a bare object with no casts or methods.

## T17. A set-null foreign key can fail inside the transaction that wrote the row

*Found 2026-09-19, building spec 14.3.3.*

Deleting a user cascades to their courses and lessons, and each of those sets a `usage_events` row's `course_id` or `lesson_id` null. If the usage row was written in the same transaction as the delete, Postgres re-checks every foreign key on it whenever any one of those set-nulls updates it, and it may do so after the course is gone but before the course's own set-null has reached the row. The delete then fails with a foreign-key violation on `usage_events`. A row written in an earlier transaction skips that re-check, so the nightly sweep never trips it. Every `RefreshDatabase` test does, because the whole test is one transaction.

**A nullable foreign key whose parent can be deleted through a cascade that also reaches the same row is `deferrable initially deferred`.** The check then runs at commit, after every set-null. `usage_events.course_id` and `lesson_id` are the examples.

## T18. Data a test suite needs before its transaction has one hook, and it is not the obvious one

*Found 2026-09-20, building spec 15.1.*

Package 15 moved prompts into the database, so every test that makes an AI call needs the 18 `prompts` rows before it runs. `RefreshDatabase` looks like it has a hook for exactly this — `afterRefreshingDatabase()` — and it is the wrong one twice over: it runs on **every** test, not once, and it runs **after** `beginDatabaseTransaction()`, so whatever it writes is rolled back with the test that wrote it. `migrateDatabases()` is the right side of the transaction but cannot be overridden from `Tests\TestCase`, because a trait's method in the subclass beats an inherited one from the parent class.

**Name a seeder: `protected $seeder = SomeSeeder::class;` on `Tests\TestCase`.** `migrate:fresh --seeder=` runs it once per process, after the migrations and before the transaction, so the rows are there for the whole run. `Database\Seeders\PromptRegistrySeeder` is the example. It runs that seeder *instead of* `DatabaseSeeder`, which is what is wanted here — nothing else is seeded into every test.

## T19. `wayfinder:generate` by hand drops the form variants the vite plugin asks for

*Found 2026-09-21, building spec 15.2.*

`vite.config.ts` configures `wayfinder({ formVariants: true })`, so every generated route gets a `.form` helper and eighteen files across `resources/js` import one. Running `php artisan wayfinder:generate` by hand regenerates without that option and strips them all, and the failure surfaces as eighteen unrelated TypeScript errors in auth and settings screens — nowhere near the route you were adding.

**Run `php artisan wayfinder:generate --with-form`, or just let vite do it.** The generated tree is gitignored, so a bad run is invisible to `git status` and only `npx tsc --noEmit` catches it.

## T20. A body that drops a declared variable fails silently, and `render()` is not the guard

*Found 2026-09-21, building spec 15.2.*

`PromptDefinition::render()` compares the *supplied* variables to the *declared* ones, and never looks at the body. So two things pass straight through it: a body interpolating `{{ x }}` that `vars` does not declare leaves `{{ x }}` in the prompt text verbatim, and a body that has stopped using a declared `{{ x }}` renders fine while the call site keeps passing the material and the model never sees it. The second is the dangerous one — nothing anywhere errors, and the prompt just quietly gets worse.

**Both directions are checked where a body is written, not where it is rendered**: `prompts:sync` refuses a file that drops a var the active version uses ([15.1.4](specs/15-prompt-registry.md)), and the editor refuses a save whose body and the file's `vars` disagree either way ([15.2.2](specs/15-prompt-registry.md)). The gap that remains: a file that *adds* a var the running body does not use is still silent, because the file's `vars` import and its body does not (ledger [D65](02-design.md)).

## T21. `cursor()` drops eager loads, and the constraint on them with it

*Found 2026-09-23, building spec 16.1.*

`Module::query()->with(['subtopics' => fn ($q) => $q->listed()])->cursor()` never runs the `with`. A cursor holds one model at a time, so it cannot eager load, and it does not say so. Each module then lazy-loads `subtopics` on first access, **without the closure**. While the eager load was only an optimisation the cost was an N+1 nobody noticed. Once it carried a rule, the picker offered every row the rule was there to hide.

**If a `with` carries a constraint, iterate with `get()` or `lazy()`, never `cursor()`.** `lazy()` chunks and does eager load.

## T22. `withCount` ignores a custom select, so it cannot count distinct

*Found 2026-09-23, building spec 16.1.*

`withCount(['courses as students_count' => fn ($q) => $q->selectRaw('count(distinct user_id)')])` still emits `count(*)`. The closure's `where`s apply and its select does not, so three courses by two students read as three students, with no error.

**Pass the expression as the column: `withAggregate('courses as students_count', DB::raw('distinct courses.user_id'), 'count')`.** An expression is used verbatim, giving `count(distinct courses.user_id)`. Larastan cannot type an aggregate alias on the model, though. When several aggregates are wanted for one screen, a grouped `DB::table` query, as `CatalogController::demand` has it, is simpler than annotating the model.

## T23. Soft deletes hide a row only from Eloquent, and change `delete()` everywhere at once

*Found 2026-09-23, building spec 16.2.*

Adding `SoftDeletes` to a model has two effects that reach code nobody is editing. **Anything that reads the table without Eloquent still sees removed rows.** Three places did: Catalog → Demand's `DB::table` query counted removed courses, and course creation's `Rule::exists('subtopics', …)` and `exists:modules,slug` accepted a removed subtopic's id from a hand-built POST. `whereHas` and route binding do respect the scope. **And every existing `delete()` on that model silently becomes a soft delete.** The demo sweep, which is meant to be the one hard delete, would have started keeping rows. The fixture seeder's reset would have broken outright, because a soft delete sets `deleted_at` without a `removal_id` and trips the check that ties the two together.

**When a model gains `SoftDeletes`, grep for its table in `DB::table`, raw joins and `exists:`/`Rule::exists` rules, and add `whereNull('deleted_at')`. Then grep for `->delete()` on it and decide each one: soft, or `forceDelete()`.**

## T24. A route's controller class may start with a backslash, depending on how the route was declared

*Found 2026-09-23, widening 13.1.2's gate test for spec 16.3.4.*

`Route::getControllerClass()` returns `App\Http\Controllers\…` for a route declared with a plain `Route::get` inside a group, and `\App\Http\Controllers\…` for one declared through a chain such as `Route::middleware('auth')->get(…)`: `RouteRegistrar::compileAction` prepends the backslash, the router does not. A test matching the class with `str_starts_with($class, 'App\\…')` therefore passed while a chained route mounted an admin controller outside the gate — it could only ever match the routes already declared the ordinary way.

**Compare a route's controller class after `ltrim($class, '\\')`. And a guard test is not proven by passing: plant the violation it exists to catch, see it fail, then remove it.**

## T25. Sorting by text under the database's collation is the slow part of a report, and neither memory nor an index touches it

*Found 2026-09-24, benchmarking spec 17.3.5 on a million `usage_events` rows.*

The performance table groups a million rows by three text columns and takes percentiles. It ran 0.8–1.0 s over thirty days and 1.3–1.7 s over ninety. EXPLAIN showed a sort spilling 20 MB to disk, so the obvious fixes were tried first. **Raising `work_mem` to 64 MB gained nothing, and made the ninety-day query worse. An index on `created_at` gained nothing either.** Asking for both percentiles as one `percentile_cont(array[…])` gained nothing too, because Postgres already shares one sort between ordered-set aggregates with the same `ORDER BY`. The cost was comparing strings under the database's language collation, a million times over.

**Group report keys with `collate "C"` when they are identifiers rather than words** — prompt keys, model ids, tiers, slugs. Byte order sorts them just as well and halved the query. And never group by an expression that only exists to fold a column into one value (`string_agg(distinct tier)`): it put that column in the sort key anyway. Make it a real group key.
