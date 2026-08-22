# OpenTutor — Frame

*Recorded 2026-08-21. Product name is a working title.*

## Situation

LLMs are excellent knowledge resources, and most people access them through a generic chat window.
Structured guided learning exists (tutors, courses), but human tutoring is expensive and often disproportionate to curiosity-driven learning goals.

## Complication

Generic chat is not built for continual guided learning: no curriculum, no assessment, no learning-specific interactions (rendered quizzes, checkpoints, non-interrupting side explanations), and no dedicated domain that signals "this is where I learn."
Nothing exists between "chat with GPT" and "hire a human."

## Question

Can a purpose-built LLM tutoring product — structured curricula, persistent knowledge tracking, and learning-specific UI — deliver enough value over generic chat that individuals will pay for it?

## Constraints

- Solo developer, AI-augmented; nights-and-weekends plus occasional work hours.
- Laravel chosen (familiarity + first-party AI SDK); confirmed in design, not just default.
- ~$100/month inference tolerance while free-for-friends; no BYOK — inference comes out of margin.
- Something usable within ~1 month of build start.
- One codebase serving browser, phone, and tablet.
- Priority when goals conflict: a product the founder uses daily that happens to be showable > breadth/flash.

## Non-goals (v1)

- Under-18 users — **deferred, not abandoned**: v1 ToS is 18+; K-12 is a phase-2 effort with its own compliance work (COPPA verifiable parental consent, state minor-safety laws, model-provider minor ToS).
- Credentials or accreditation.
- Human tutors, classrooms, social features.
- Licensed or imported content — the agent generates everything.
- Homework-answer positioning ("teach me algebra," never "do my worksheet").
- BYOK.
