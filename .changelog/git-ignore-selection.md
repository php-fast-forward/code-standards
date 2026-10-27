---
category: changed
type: minor
pull_request: 39
---

Apply the consumer's root and nested Git ignore rules dynamically to tracked candidates in full and changed scans, including force-added matching files. Preserve Git's negation, anchor, escape and directory semantics without a second parser or host-global excludes. Replace generated-directory name exclusions with consumer-owned rules while retaining structural Git metadata and source-containment protection. Ignore-rule changes, removals and renames trigger a complete eligible tracked scan; case-insensitive policy-name detection prevents missed invalidation on case-folding filesystems.
