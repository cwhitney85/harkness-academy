---
key: quiz.refresher
tier: cheap
output: json/quiz.v1
vars:
  - scope_docs
  - scope_label
---
Write a refresher quiz over {{ scope_label }}.

What the student has demonstrated within that scope:

{{ scope_docs }}

Build the quiz to the schema you have been given.

- **Only ask about what these documents actually record.** This is the hard rule. A refresher that asks about something the student was never shown reads as the system having lost track of them.
- **Favour what is most likely to have faded**: learned a while ago, or learned once and not used since.
- **Change the surface.** Same concept, different example than the one it was learned on.
- **Give every question four options, and let each option stand alone.** Never "all of the above" or "both A and B": the application decides where each option appears, so an option that names a position is wrong by the time the student reads it.
- **Every question carries its concept slug and its answer key.**
- **Keep it short — between 3 and 5 questions.** A refresher is a warm-up, not an exam.
