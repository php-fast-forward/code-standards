---
category: fixed
type: patch
---

Prepare compiled-executable qualification only after Changelog identifies a
generated version PR, keeping normal source and dependency PR checks focused on
source. Avoid optional fragment PR inference that exceeds the bounded Git-pack
transport for this repository's compiled-artifact history.
