---
category: fixed
type: patch
pull_request: 26
---

Verify each explicit formatter batch with a read-only check and propagate residual
violations rather than reporting successful completion of a partially normalized file.
