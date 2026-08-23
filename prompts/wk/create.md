---
key: wk.create
tier: mid
output: markdown
vars:
  - interview_transcript
  - subtopic
  - skill_level
---
Write a working-knowledge document for a student beginning a course on {{ subtopic }} at the {{ skill_level }} level, based on this interview:

{{ interview_transcript }}

Output markdown with exactly three sections, in this order: `## Solid`, `## Shaky`, `## Gaps`.

- **Solid** — what they demonstrated unaided. Only what the transcript actually shows; a claim they made about themselves is not evidence.
- **Shaky** — what they half-have: right answer by the wrong route, right idea with the wrong vocabulary, or correct in one framing and lost in another. Confident wrong answers belong here, named as such.
- **Gaps** — what did not come up or came up empty. Say which gaps matter for this course and which are simply out of scope.

Use bullets. One claim per bullet, written so a later lesson can act on it. Quote the student where a quote is more precise than a paraphrase. Do not pad a section to make it look balanced — an empty section says something true.

This document is read by the model that plans and teaches the course. Write it for that reader, not for the student.
