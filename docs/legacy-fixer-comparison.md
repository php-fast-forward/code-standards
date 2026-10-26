# Resolved legacy fixer comparison

This reference extends issue #5 with actual fixer instances at fixed source and
package references. It does not select a broader default profile or claim parity
with the DevTools Rector/PHPDoc pipeline. The initial non-risky preset stays the
default. No consumer migration follows from these results.

The [machine-readable inventory](reference/legacy-fixer-inventory.json) contains
every active fixer, fully resolved configuration including upstream defaults,
priority, reported risky flag, package reference and source-file SHA-256. Paths
are relative to source packages; no developer paths or credentials are included.
This is recorded evidence, not executable configuration or a consumer DSL.

## Reproduction boundaries

Three separate PHP 8.5.11 processes used one disposable Composer project declaring
PHP `^8.3`. No process loaded another tool's autoloader. Reflection verified each
loaded fixer against its own installation. Source commits are in the inventory.

CoiSA expanded `DefaultSet('')->getRules()` with native RuleSet/FixerFactory;
no header was supplied. DevTools loaded its actual ECS builder, booting the
ECS 13.0.4 LazyContainerFactory and compiler passes. Only cache location and
parallelism were redirected. Its additional Generic GitMergeConflictSniff is
outside the fixer counts. Code Standards booted its ECS 13.3.8 container/preset.
Configured fixer state was inspected after native normalization; null means
that fixer has no stored configuration property.

Both legacy checkouts have untracked local Composer locks. These local installs
are evidence, not published lockfiles or fresh-install reproducibility promises.
Reproduce the recorded package references in separate installations before
updating the inventory; do not combine dependency graphs. Verify source-file
hashes as well as package metadata.

The active DevTools fixer is **bundled PHP-CS-Fixer 3.92.4**, not its separately
installed root PHP-CS-Fixer 3.95.1. The active reference is
`9e7488b19403423e02e8403cc1eb596baf4673b0`. CoiSA and Code Standards load
PHP-CS-Fixer 3.95.27 (`1d0c68ee5f37446d8ad5970b82384cd1bb0a3317`). DevTools
also loads bundled Symplify Coding Standard 13.0.0
(`bd7c36af1e96eecd08cfcc0a772a19767aa02300`). Each rule records its real origin;
root metadata must not silently stand in for the loaded code.

## Observed deltas

| Profile | Active fixers | Reported risky |
| --- | ---: | ---: |
| CoiSA | 230 | 54 |
| DevTools ECS | 216 | 43 |
| Initial Code Standards | 48 | 0 |

The legacy profiles share 194 names; CoiSA has 36 additional rules and DevTools 22.
Seventeen shared names have different normalized configurations. All 48 initial
rules occur in DevTools; CoiSA lacks `visibility_required`. Membership does not
establish behavior equivalence across package versions.

Differences include PHPDoc alignment (CoiSA vertical, DevTools/Code Standards
left), typed-parameter annotation generation, strict types, empty loop layout,
PHPDoc ordering and trailing-comma elements. Both legacy profiles normalize
multiline arguments; Code Standards preserves blank lines between attributed
parameters with `on_multiline=ignore`, covered by its existing golden regression.

CoiSA enables author annotation removal, immutable DateTime conversion and
protected-to-private conversion. DevTools preserves author annotations and
mutable DateTime at this ECS stage, but still enables protected-to-private.
The initial preset preserves all three, as its goldens demonstrate. Upstream
`isRisky()` is metadata, not a guarantee of unchanged public API:
`protected_to_private` reports false despite changing visibility.

No header is active in the CoiSA snapshot because its header argument is empty.
A supplied header adds the PHPDoc `after_open` writer and year/range normalization.
DevTools' separate `.docheader`/PHPDoc pass has another placement contract.
Neither is part of the initial Code Standards default.

## Remaining decision

Keep #5 open for behavioral comparisons of the complete legacy pipeline,
header/year and Finder fixtures, actual override precedence and target-version
variations, plus a separately authorized choice of any opt-in profile. Rector
sets/custom rules are semantic work; optional Twig/magic-value and type-analysis
ownership remain independent. Do not broaden the default or regenerate this
inventory merely to silence a dependency-update failure.
