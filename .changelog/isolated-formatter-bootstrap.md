---
category: fixed
type: patch
---

Checks and fixes through ECS's public APIs without implicitly loading a separate
consumer vendor autoloader. Preserves consumer cwd, native configuration and cache
behavior, explicit custom-checker opt-in, and the tool's own Composer bootstrap.
