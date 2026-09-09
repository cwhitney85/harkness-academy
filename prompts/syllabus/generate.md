---
key: syllabus.generate
tier: top
output: json/syllabus.v1
vars:
  - wk_doc
  - criteria_doc
  - skill_level
  - depth_params
---
Design a course syllabus at the {{ skill_level }} level.

What the student already knows:

{{ wk_doc }}

What the student wants:

{{ criteria_doc }}

Depth parameters for this course:

{{ depth_params }}

Build the syllabus to the schema you have been given. Hold to these rules:

- **Start from the edge of what they have, not from zero.** Anything the working-knowledge document lists as solid is a foundation to build on, not a lesson to teach. Shaky items get repaired inside a lesson that needs them, not a remedial lesson of their own.
- **Every lesson earns its place against the criteria document.** If you cannot name the goal a lesson serves, cut it.
- **Declare a concept slug for every concept you introduce.** Lowercase, hyphenated, stable. These slugs are the vocabulary that checkpoints, summaries, exams and refreshers all key on, so a slug that appears in two lessons must mean the same thing in both.
- **Order by dependency, then by interest.** Where two lessons are equally ready to be taught, teach the one closer to what the student said they cared about.
- **Respect the depth parameters.** They set the size of the course. Skill level sets what is taught and how, never how much.
- **Note a mismatch between their goals and their subtopic rather than refusing.** If the student's stated goals do not fit the subtopic they chose, build the best course you can and record the mismatch in the syllabus. It is a flag, not a blocker. This is the only mismatch left to find here: whether the subtopic belongs under the subject it was started in was settled before the course existed.
