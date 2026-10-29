---
category: fixed
type: patch
---

Connect repository publication to Changelog's publication-only App inputs using an immutable API-compatible candidate revision, so missing publication credentials fail closed instead of silently publishing with GITHUB_TOKEN. Stable adoption still requires the compatible upstream release and approved environment/key configuration.
