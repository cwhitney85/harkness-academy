---
key: wk.update
tier: mid
output: markdown
vars:
  - prior_wk_doc
  - lesson_summary_json
  - homework_results
---
Update a student's working-knowledge document with what the last lesson showed.

The current document:

{{ prior_wk_doc }}

What the lesson established:

{{ lesson_summary_json }}

The student's homework record in this subtopic, with how each question went. This is the whole record, not only what is new — a result you have already accounted for should stay accounted for:

{{ homework_results }}

Output the complete updated document — this replaces the old one, so anything you leave out is forgotten. Keep the same three sections in the same order: `## Solid`, `## Shaky`, `## Gaps`.

- **Move items, do not just append.** A gap the lesson filled moves to Solid or Shaky depending on how well it landed. A shaky item the student used correctly under pressure moves to Solid.
- **Demote where the evidence says to.** If the lesson showed something listed as solid is not, move it down and say what revealed that.
- **Keep the wording of untouched items exactly as it was.** Only rewrite a bullet the lesson actually changed.
- **Stay evidence-bound.** Only what the summary and the homework support. Do not infer that adjacent knowledge improved.
- **Homework is evidence about earlier lessons, not this one.** It was set after lessons this document already absorbed, so it confirms or corrects what is written there rather than adding something new. A concept marked solid that was then missed on homework moves down; one already recorded as shaky and since answered correctly moves up.

Write for the model that will plan and teach the next lesson.
