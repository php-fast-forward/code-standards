---
category: added
type: minor
---

Check this repository's root Composer metadata in CI and expose an opt-in `normalize-composer` boolean in the reusable check workflow. Reuse the reviewed installed tool in check mode, independent of changed PHP selection, without applying changes, maintaining locks or installing consumer packages.
