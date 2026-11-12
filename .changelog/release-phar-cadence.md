---
category: fixed
type: patch
---

Build runtime-affecting pull requests once and qualify the same executable on
all supported PHP versions before automatically refreshing their compiled
distribution. Skip rebuilds for documentation-only changes, refuse refreshes
of closed or superseded branches, and require successful base quality before
version preparation. Preserve automatic fragment pull-request references.
