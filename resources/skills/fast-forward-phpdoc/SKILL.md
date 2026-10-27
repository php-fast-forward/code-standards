---
name: fast-forward-phpdoc
description: Write, review or clean PHPDoc in Fast Forward PHP projects, especially concise summaries, abstract interface contracts, descriptive tags and inherited documentation, while preserving behavior and public APIs.
license: MIT
compatibility: Documentation authoring requires source, contracts and tests from the target project. Optional Code Standards check/fix examples use the verified 0.3.2 baseline, PHP ^8.3 and Composer runtime API ^2.2; inspect the installed CLI before use.
metadata:
  api-baseline: PHPDoc authoring; optional Code Standards check/fix 0.3.2
  repository: https://github.com/php-fast-forward/code-standards
---

# Fast Forward PHPDoc

Use this skill to improve source documentation without changing execution, public
names, namespaces, member order, native types or compatibility. It works from this
payload alone and does not require DevTools, an installed global skill or a renderer.

## Ordinary authoring route

1. Read the target project's instructions, Git diff, relevant symbols, parent
   contracts, callers and tests. Establish intended behavior separately from the
   behavior observed in one implementation. Report contradictions or unknowns.
2. Write a short, objective, single-sentence summary of the element's purpose.
   Add only useful semantics beyond it, in prose or the appropriate tag, once.
3. For an interface, describe observable obligations of **any** conforming
   implementer. Keep concrete class names, incidental algorithms and mechanisms
   on implementations. Apply the [contract checklist](references/contracts.md)
   before introducing requirements or tool-specific types.
4. Preserve descriptions, units, sentinels, richer types, consumed annotations
   and inheritance markers. Read [cleanup and examples](references/cleanup.md)
   before deleting a tag or changing inherited documentation. Keep documentation
   in the project's language; Fast Forward source documentation uses English.
5. Review the diff against the original meaning and another plausible conforming
   implementation. Run the project's relevant checks and inspect rendered
   semantics when rendering or inheritance changes. Report checks not available.

For example, retain the semantic contribution even though the signature is typed:

```php
/**
 * Reads the configured lifetime.
 *
 * @return int Lifetime in seconds; zero disables expiry.
 */
public function lifetime(): int;
```

Removing the tag above would lose the unit and sentinel. Do not infer those facts
from a method name when the target API has no established contract.

## Formatting and verification

Preserve the project's existing header and legal notices. Do not invent ownership,
dates, license text, imports, suffixes or a universal header requirement. Formatting
follows the selected project configuration, not the old DevTools preset.

When Code Standards is already installed, inspect its version and help, then run:

```sh
composer show fast-forward/code-standards
vendor/bin/code-standards help check
vendor/bin/code-standards check --all
git diff --check
git diff
```

The optional check/fix route has a verified 0.3.2 baseline. Preserve an existing
consumer constraint; do not upgrade it just to follow this procedure. If absent,
installation requires an explicit request: `composer require --dev fast-forward/code-standards`.
Check does not change source. Fix requires an explicit request; inspect its diff
and rerun check. A clean formatter cannot establish that a contract is true, prove
RFC wording is appropriate or guarantee preservation under arbitrary overrides.

Semantic refactoring is a separate opt-in task. Inspect the installed command and
native Rector rules before using it; never run it as implicit PHPDoc cleanup.
Executable configuration and consumer bootstraps are not sandboxed by dry-run.

Use the [verification checklist](references/verification.md) for changed contracts,
failures and a bounded review report. Return actual evidence and open questions;
do not publish speculative guarantees merely to make documentation look complete.

## Distribution

This directory is the canonical `fast-forward-phpdoc` payload, including its
license and relative references. Composer advertises `resources/skills` through
`extra.skills.source`. A consumer may explicitly select LLM Skills for discovery
and installation; shipping this payload does not activate it, install a runtime,
grant plugin approval or authorize a consumer rollout. It has no global-skill
dependency and provides no installer or synchronization command.
