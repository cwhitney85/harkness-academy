---
description: Start a build session scoped to one spec package (usage: /package 2, optionally /package 2 items 2.1-2.4)
---

Read PLAN.md, then the spec for package $ARGUMENTS in docs/specs/.

This session's scope is package $ARGUMENTS only (narrow further to the listed items if the arguments name any). Build each item to its done-condition, tests passing as you go.

Rules for this session:
- Do not modify other packages. If you need an interface that hasn't landed on master, stop and say so.
- If you hit a spec gap, or a recorded decision seems wrong in practice, stop and ask instead of silently diverging (decisions live in docs/02-design.md).
- Commit at done-condition granularity: one spec item (or small cluster) per commit, spec item number in the message (e.g. `2.4: caching spike — workaround for laravel/ai#119`). No signing, co-author trailers, or attribution lines.

End of session: update PLAN.md's status row for this package and list which done-conditions now pass.
