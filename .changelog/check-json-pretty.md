---
category: added
type: minor
---

Add explicit `check --format=json-pretty` presentation with the same versioned result and exits as compact JSON, using a dedicated check result renderer.

Centralize public output-format identifiers in a backed enum and make check-result construction explicit with named arguments, preserving each command's supported formats and native schemas.

Offer enum-derived shell-completion suggestions while retaining explicit format validation and existing invalid-input diagnostics.
