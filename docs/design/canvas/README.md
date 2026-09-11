# Design canvas — source of record

The visual language of Harkness Academy lives here as source, and is read as a published canvas:

**https://claude.ai/code/artifact/5d5d7052-86b8-4230-8c2a-606cba1b6f46**

Each `.dc.html` file is one artboard. `canvas.json` places them, names the pages, and picks the launch view. Together they are the input to the canvas editor — not a build artefact, and not generated from anything else.

## Pages

| Page | Holds |
|---|---|
| **A · materials** | The settled design: lesson, photocopy quiz, blue-book exam (closed and opened), the four checkpoint treatments, the two phone reductions, and the parked legal pad. |
| **A · dosage** | Three settings of direction A — Restrained, Spirited, Full. Superseded, kept for the record of what was cut. |
| **First directions** | The original three directions (Chalkboard, Foolscap, Assembly Hall) across lesson, syllabus and homework. Superseded. |
| **B · brand** | The name, the mark and the front door ([D44](../../02-design.md), spec 8.13). Nothing on it is chosen yet: the wordmark, the motto's placement, the page and the ground are each isolated on their own artboard. |

## Rebuilding the canvas

Invoke the `design` skill, then seed a fresh copy of its payload from these files — every `.dc.html` in this directory, plus `canvas.json`:

```
cd docs/design/canvas
ARGS=(); for f in *.dc.html; do ARGS+=(--artboard "$f"); done
node "<skill base>/seed-canvas.mjs" \
  --template "<skill base>/payload.template.html" \
  --out harkness-academy-design-study.html \
  --title "Harkness Academy Design Study" \
  "${ARGS[@]}" \
  --canvas canvas.json
```

`Main.dc.html` is the entry artboard (the Foolscap lesson, kept for its file identity). Publish the seeded file with the Artifact tool, passing the URL above so it updates in place rather than creating a second canvas.

Re-seed from **these files**, not by extracting the published page — the published page is downstream.

## The rule

A change to the visual language changes this directory **and** `resources/css/app.css` in the same commit. Spec 8.1.3.
