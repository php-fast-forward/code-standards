---
category: added
type: minor
---

Add opt-in `normalize-composer` metadata checking and explicit `--apply`, using the composer-normalize library while retaining literal dependency constraints and significant ordering. Keep PHP check/fix unchanged; never execute Composer scripts/plugins or update locks, and report stale hashes before/after explicit manifest writes.

Declare the added runtime libraries with compatible caret constraints while retaining the verified locked package versions.

Keep rejected metadata contents out of invalid-JSON diagnostics, including verbose output.
