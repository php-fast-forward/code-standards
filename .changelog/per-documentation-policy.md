---
category: changed
type: minor
pull_request: 40
---

Use ECS 13.3's native non-risky PER Coding Style 3.0 set in place of PSR-12, preserving explicit command-attribute spacing and callback-last overrides. Keep destructive PHPDoc cleanup rules skipped so descriptions, inheritance markers, analysis types and intentional annotations survive formatting. Remove inactive conversion/global-import skips without implicitly enabling those rules.
