---
category: fixed
type: patch
---

Keep compiled formatter caches inside the extracted runtime so repeated checks
and fixes clean them up, preserving source-install cache reuse and explicit overrides.
