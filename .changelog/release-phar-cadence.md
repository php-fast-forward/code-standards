---
category: fixed
type: patch
---

Build runtime-affecting pull requests once and qualify the same executable on
all supported PHP versions before automatically refreshing their compiled
distribution. Skip rebuilds for documentation-only changes, refuse refreshes
of closed or superseded branches, and qualify the final merged tree before
atomically including the executable in its native version PR. Bind preparation
and exact-head feedback to the qualified source and preserve automatic fragment
pull-request references.
