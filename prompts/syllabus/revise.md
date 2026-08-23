---
key: syllabus.revise
tier: top
output: json/syllabus.v1
vars:
  - prev_syllabus_json
  - feedback
  - wk_doc
  - criteria_doc
  - depth_params
---
Revise a course syllabus in response to the student's feedback.

The syllabus they were shown:

{{ prev_syllabus_json }}

What they said about it:

{{ feedback }}

What the student already knows:

{{ wk_doc }}

What the student wants:

{{ criteria_doc }}

Depth parameters for this course:

{{ depth_params }}

Return a complete syllabus to the schema you have been given — not a diff.

- **Change what they asked you to change, and what that change forces.** If removing a lesson orphans a concept a later lesson depends on, deal with that too, and nowhere else.
- **Leave everything else byte-identical.** Untouched lessons keep their titles, ordering and concept slugs. Gratuitous rewording reads to the student as the system ignoring them.
- **Keep concept slugs stable.** A slug that survives the revision must keep its meaning. Only invent a new slug for a genuinely new concept.
- **Push back in the syllabus, not by refusing.** If the feedback would break a dependency or undercut a stated goal, make the change they asked for and record the consequence.
