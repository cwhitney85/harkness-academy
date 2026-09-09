# dogfood-repair-5 — plan

*Disposable. Delete this file in the commit that lands the unit.*

**Scope:** [5.7](../specs/05-lesson-engine.md) checkpoint voice · [5.8](../specs/05-lesson-engine.md) literal escape sequences on screen

## Items

- [x] **5.7** — `prompts/checkpoint/generate.md` states the voice rule; generated questions say "we talked about" or "I mentioned", never "the tutor".
- [ ] **5.8** — `—` never reaches the screen as literal text; this file records where the escape entered.

## Log

**5.7.** One bullet in `prompts/checkpoint/generate.md`, placed with the other phrasing rule. No new
test: `PromptRenderFixturesTest` already locks the rendered bytes, so removing the rule fails a test
and shows up in review (4.3.1's precedent, commit cc5b4e8).
