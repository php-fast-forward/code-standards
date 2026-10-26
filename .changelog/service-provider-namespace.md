---
category: changed
type: minor
pull_request: 28
---

Place the tool's StandardsServiceProvider and composition tests in the
ServiceProvider namespace, matching the provider's responsibility before the
first publication. Third-party dependency-injection APIs remain unchanged.
Use the published Container factories for lazy service bindings, preserving
native autowiring, explicit scalar inputs and consumer overrides.
