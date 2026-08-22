# OpenTutor

LLM personal-tutor PWA. Laravel 13 + Inertia/React + Postgres. Anthropic-direct via the laravel/ai SDK. Solo founder (Colin) + AI-augmented sessions.

## Before writing any code

1. Read [PLAN.md](PLAN.md) — status table, dependency graph, global acceptance test.
2. Read the spec for the package you're working on (`docs/specs/`). Spec item numbers (2.4, 5.2.2, …) are the shared vocabulary — use them in commits and discussion.
3. Decisions are settled in [docs/02-design.md](docs/02-design.md) with rejected candidates and rationale. Don't re-litigate; if a decision proves wrong in practice, surface it to Colin instead of silently diverging.

## Standing conventions

- **No model IDs outside `config/ai.php`** — call sites name capability tiers (`cheap`/`mid`/`top`) through the AI gateway (spec 2.1).
- **No memory access outside the path API** (spec 3.2). Model-facing docs live in the memory tree; app-queried state lives in relational rows.
- **Prompts are files in `prompts/`** with frontmatter (spec 2.3.1) — never inline strings.
- **Memory writes are workflow-initiated** — the model never decides when to write memory.
- **Fail-open rule**: fail open only when failing closed would destroy student progress (spec 4.5.1).
- **GUIDANCE.md is model-facing only** — no route or component may read it.
- **Skill level (101/201/301) is pedagogy; plan is economics** — never couple them (spec 4.5.6).
- Every AI call must log to `usage_events` (spec 2.2) and use a prompt key from the inventory (spec 2.3.2).
- Anthropic-specific feature usage gets an entry in `docs/anthropic-specific.md` (spec 2.5).

## Git conventions

- Default branch is `master`. Serial solo work commits directly to `master`; branches only for worktree lanes and risky spikes (ledger W4).
- Commit at done-condition granularity: one spec item (or small cluster) per commit, spec item number in the message (e.g. `2.4: caching spike — workaround for laravel/ai#119`).
- No signing, co-author trailers, or attribution lines in commit messages.

## Session ritual

Start a build session with `/package <n>` (see `.claude/commands/package.md`).
End of session: update PLAN.md status table (package status, done-conditions checked, branch if any).

## Parallel lanes (worktrees)

A lane may open only when every interface it consumes has landed on master. Colin reviews and merges every lane — never auto-merge.
