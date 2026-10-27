---
category: changed
type: patch
---

Uses each external Action's published major channel: checkout v7, setup-php v2
and Changelog's released v0 branch channel. Preserves workflow permissions,
unprivileged PR checks and runtime provenance from the called job's actual SHA.
