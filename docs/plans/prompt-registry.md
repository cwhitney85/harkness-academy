# Prompt registry — the editor (15.2)

*Opened 2026-09-21. Scope: 15.2 items. Base branch `pkg/15-prompt-registry`.*

## The design reversal that opened this session

15.1 landed on 2026-09-20 with D54's shape: files are the reviewed baseline,
the database is what runs, and a deploy never overrides a live edit. Building
15.2.5 on it found the reconciliation unclosable. `prompts:sync` compares a
file against the newest `file` version's hash (15.1.2), so after
`prompts:pull` writes a live edit into the file, the next deploy sees a
changed file, makes a version whose body duplicates the active one, and flags
it — 15.2.5's done-condition ("pull, commit, sync — no new version") cannot
pass.

Four candidate fixes, in the order they were put to Colin:

1. **Adopt the pulled text as a new file version.** No schema change; costs a
   version row whose body duplicates its predecessor. Colin: that stretches
   what a version means. Agreed — the row records "this text is now in git",
   which is not "a text this prompt ran". Two facts, one table.
2. **Compare the file to the active version first, then to the newest file
   version.** No duplicate row, no migration, 15.2.5 passes literally.
   Residual: after a pull the active version still records `source: admin`,
   so the *next* file edit waits for one manual activation.
3. **Repo-only, editor read-only.** Deletes the most structure. Argued for it
   (live editing routes around the test suite; eight of eighteen prompts must
   produce schema-valid JSON; the deploy is two minutes; option 2 → option 1
   is additive but the reverse is not). Colin: no.
4. **Chosen: the database owns the body.** Files seed version 1 and are an
   export target; every later change is an editor save. Sync never compares
   or reconciles, because there is only one writer.

A hosted registry (Langfuse, LangSmith, PromptLayer, Braintrust) was
re-examined — Colin had forgotten D54 rejected it. It stays rejected: the
half of those products worth paying for is the eval/playground loop, which
15.3.6 marks WONT, and their traces would carry student transcripts.

## What the reversal tightened

**15.1.4 now checks the active version whatever its source.** Under D54 only
an admin version could be running a body the file had moved away from, so the
guard skipped file-sourced actives. Now the file's body never re-imports, so
version 1 can outlive its own file: change `prompts/x.md` to drop a var, and
sync writes the narrower `vars` while version 1's body keeps interpolating
the dropped one. `render()` does not throw on that — it compares supplied
keys to declared vars, so an undeclared `{{ placeholder }}` is simply left
in the prompt text verbatim. Silent corruption, and the guard is what stops
it.

## Known gap, not built

**A file that adds a var the active body does not use is silent.** When a code
change passes a new variable, the file's `vars` imports and its body does
not, so the new material never reaches the model and nothing errors. Raised
twice; Colin did not ask for it. The fix is one warning line in
`prompts:sync`. Left out deliberately — no item requires it.

## Session log

- Plan file opened; 15.1 reversal agreed.
