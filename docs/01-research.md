# OpenTutor — Research Findings

*Recorded 2026-08-21. Confidence labels: confirmed / probable / uncertain / unknown.*

## Thread 1 — Competitive landscape

- **ChatGPT Study Mode (Jul 2025) and Gemini Guided Learning (Aug 2025) exist** (confirmed). Both do Socratic questioning, quizzes, pacing — inside the chat window. The baseline "chat isn't for learning" argument is weakened.
- **Critics fault exactly what OpenTutor builds** (probable): no lesson planning, no curriculum spine, no persistence toward goals, no spaced repetition. Key source: moderndescartes.com/essays/study_mode ("a tutor that didn't prep for the session and is just winging it"). Founder tested Gemini Guided Learning firsthand and was unimpressed; core loop judged to hold up.
- **Khanmigo**: $4/mo, structure comes from Khan's K-12 content library; does not generate arbitrary adult courses (confirmed pricing; probable on limits). Adult "learn anything on a guided track" lane comparatively open.
- **Indie layer** (TutorAI/Learn Anything ~$14/mo, Xplain, YouLearn…) is crowded but shallow — outline generators with chat attached; none found with full assess→syllabus→lessons→exam→knowledge-tracking loop (uncertain — review coverage, not hands-on).
- **Pricing anchors**: $4/mo (Khanmigo) → ~$15/mo (indies) → $20/mo (ChatGPT Plus) (confirmed endpoints).
- Non-competitors: B2B "AI course creator" tools (Coursebox etc.) — different market.
- Unknowns: whether Study Mode + Memory closes the persistence gap over time; indie retention/willingness-to-pay.

## Thread 2 — Stack

- **laravel/ai SDK**: official, shipped Feb 2026, stable with Laravel 13 (Mar 2026). Agents (instructions/memory/tools, SDK-run ReAct loop), structured output, streaming + broadcasting, queued AI jobs, conversation persistence, testing fakes, 14 providers incl. Anthropic + OpenRouter, pgvector (confirmed). Built on Prism (battle-tested provider core). ~5 months stable — rough edges unknown until built on.
- **Vercel AI SDK** (TS): generative UI (model streams React components) maps directly onto lesson checkpoints; largest ecosystem (confirmed). Rejected in design for stack-familiarity + timeline; pattern approximated via structured outputs + React.
- **laravel-openui** (github.com/RanaMoizHaider/laravel-openui): 5 commits, React-renderer-specific, early-stage (confirmed). Pattern reference only, not a dependency.
- **Anthropic PHP SDK** exists, full feature coverage incl. tool runner (confirmed). Claude Agent SDK is TS/Python only — irrelevant (filesystem/coding agents).

## Thread 3 — Model sourcing & cost

- **OpenRouter**: zero inference markup; 5.5% fee on credit purchases (min $0.80). Anthropic caching passthrough appears supported (probable, untested).
- **Pricing (confirmed via official reference, Aug 2026)**: Sonnet 5 $3/$15 per MTok, Haiku 4.5 $1/$5, Opus 5 $5/$25. Competitors: GPT-5.6 tiers $5/$30, $2/$12, $0.20/$1.20; Gemini 3.1 Pro $2/$12 (probable, aggregators).
- **Cost per 45-min lesson (estimate, ±3×; assumptions: 15–20 calls, ~375K cumulative input, ~20K output)**: Sonnet ~$1.45 naive / ~$0.65 cached; Haiku ~$0.50/$0.20; Opus ~$2.40/$1.10.
- **Consequences**: prompt caching is load-bearing (~65 vs ~150 Sonnet lessons per $100); session-per-lesson + markdown summaries is also the cheap architecture; model-mix routing (cheap models for quizzes/explainers) is the main margin lever.
- **Known bug**: laravel/ai#119 — `cache_control` not passed on system prompts (~90% extra input cost if unaddressed). Spike scheduled first in build order (spec 2.4).
- **Anthropic-specific features**: memory tool (`memory_20250818`) matches the WORKING_KNOWLEDGE design but is provider-specific; replicable in ~a day of custom tools. Task budgets (beta) = server-tracked token countdown; no Laravel/Prism surface found; Prism's Anthropic docs trail the current API (stale thinking/budget_tokens shapes) — PHP layer lags frontier betas.
- **Token-count prompts ("in N tokens or less") are unreliable**; working levers: structural instructions, `max_tokens` (hard truncation), effort parameter, planning-time depth.
- **Context management**: no fifth technique beyond caching, boundaries-summarization, context editing, small-model routing. RAG unnecessary at v1 (memory is small, path-addressable — the path *is* the retrieval); pgvector available later for cross-module semantic recall.

## Thread 4 — PWA vs native

- App stores take 15–30% of subscriptions; bare PWA can't list on Apple's store; wrapped (TWA/Capacitor) listing possible later at the cost of the fee (confirmed).
- iOS PWA limits: manual Add-to-Home-Screen (no install prompt), push only post-install (iOS 16.4+), push unavailable in EU (probable), no background sync — none load-bearing for a streamed-text product except push.
- Push gap = retention gap; v1.1 mitigation: daily email nudge.
- One codebase, no store review, instant updates — fits solo/one-month constraint.

## Standing unknowns

1. Real token profiles per lesson — instrument from day one (spec 2.2), replaces the ±3× estimate.
2. laravel/ai maturity — discovered by building.
3. Depth-gated pricing reception — post-v1.
4. OpenRouter caching passthrough fidelity — moot unless provider strategy changes.
