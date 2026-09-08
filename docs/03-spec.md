# OpenTutor — v1 Specification (index)

*The spec was split along WBS package boundaries (design decision W2, 2026-08-21). Numbering is preserved — spec item 5.2.4 lives in `specs/05-lesson-engine.md` under 5.2.4. Progress is tracked in [/PLAN.md](../PLAN.md), which also holds the global acceptance test, dependency graph, order of attack, and WONTs.*

| Package | File |
|---|---|
| 1 Foundation | [specs/01-foundation.md](specs/01-foundation.md) |
| 2 AI Service Layer (incl. 2.3 prompt inventory) | [specs/02-ai-layer.md](specs/02-ai-layer.md) |
| 3 Memory Subsystem | [specs/03-memory.md](specs/03-memory.md) |
| 4 Course Pipeline (incl. 4.5 syllabus contracts) | [specs/04-course-pipeline.md](specs/04-course-pipeline.md) |
| 5 Lesson Engine (incl. 5.2 wire protocol, 5.4 completion pipeline) | [specs/05-lesson-engine.md](specs/05-lesson-engine.md) |
| 6 Assessment | [specs/06-assessment.md](specs/06-assessment.md) |
| 7 PWA Shell | [specs/07-pwa.md](specs/07-pwa.md) |
| 8 Interface (incl. 8.2 fixtures, 8.3 design language, 8.4 material system) | [specs/08-interface.md](specs/08-interface.md) |
| 9 Documentation System (tiers, ledger index, disposable plans) | [specs/09-documentation.md](specs/09-documentation.md) |

Cross-package rules that live in the packages but bind globally:
- **Fail-open rule** (4.5.1): fail open only when failing closed would destroy student progress.
- **Concept-slug vocabulary** (4.5.2): declared in the syllabus, consumed by checkpoints, summaries, exams, refreshers.
- **Tier indirection** (2.1.1): no model IDs outside `config/ai.php`.
- **Memory door** (3.2): no memory access outside the path API.
- **Design source of record** (8.1.3): the canvas in `docs/design/canvas/` and `resources/css/app.css` change together.
