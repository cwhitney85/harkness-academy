# Spec 6 — Assessment

*Provides: quiz service (6.1) — the fixed contract parallel lanes build against. Consumes: 2, 3, 4 (syllabus concepts), 5 (SUMMARY docs, completion pipeline). See PLAN.md for status.*

- **6.1 MUST** — Polymorphic `quizzes` + `quiz_attempts` serving all four types (checkpoint / homework / final / refresher) through one service. *Done: four types create rows via one service.*
- **6.2 MUST** — Homework: quiz + suggested reading on lesson completion (rides 5.4.1); result feeds next WK update. *Done: homework attempt visible in WK diff.*
- **6.3 MUST** — Final exam: scoped by `final_exam_concepts`, gates `completed`. *Done: pass flips state; fail records attempt, course stays `active`.*
- **6.4 MUST** — Refresher quizzes, subtopic + lesson grain, from WK + SUMMARY docs. *Done: lesson refresher questions traceable to its summary.*
- **6.5 SHOULD** — Module-grain quiz via 3.3 pointer-walk. *Done: 2-subtopic module quiz draws from both WKs.*
