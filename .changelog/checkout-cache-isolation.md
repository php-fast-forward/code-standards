---
category: fixed
type: patch
---

Isolate the default ECS cache by executing checkout so another project's
configuration invalidation cannot erase its cache, preserving explicit overrides.
