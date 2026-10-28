---
category: fixed
type: patch
---

Use caret constraints for Container, PHPStan and Rector. Raise the Rector/PHPStan minimums together to the compatible 2.8.2/2.3.3 pair, replacing the historical patch-line restrictions without allowing the known parser failure.

Keep empty and fully skipped configurations rejected before processing with Rector's native active-rule check. Qualify the new native file-cache behavior without changing consumer-owned source/configuration/lock preservation.
