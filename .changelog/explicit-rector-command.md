---
category: added
type: minor
---

Add a separate opt-in native Rector command with dry-run by default, explicit
configuration and apply authority, empty-rule initialization protection and
post-apply verification. Require an explicit native consumer PHP target before
processing; keep target PHP, rules and caches consumer-owned;
formatting check/fix never invoke semantic refactoring.
Allow an explicit positive per-subprocess timeout without disabling bounds;
execution timeouts fail with status 1, while cache configuration remains optional.
