---
category: added
type: minor
---

Add opt-in `normalize-composer` metadata checking and explicit `--apply`, using the composer-normalize library while retaining literal dependency constraints and significant ordering. Keep PHP check/fix unchanged; never execute Composer scripts/plugins or update locks, and report stale hashes before/after explicit manifest writes.
