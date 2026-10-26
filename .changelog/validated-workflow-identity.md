---
category: fixed
type: patch
pull_request: 13
---

Validate the native executing-job JSON protocol before emitting immutable checkout
identity, rejecting missing, ambiguous or malformed values without custom validators.
