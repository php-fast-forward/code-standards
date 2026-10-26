# Policy boundary and comparison

Source evidence read for this extraction:

| Repository | Inspected local commit | Relevant paths |
| --- | --- | --- |
| php-fast-forward/dev-tools | `35b5f78ac63813ec07aa3fb3eda08751541e28e1` | `src/Config/ECSConfig.php`, `src/Config/RectorConfig.php`, `.php-cs-fixer.dist.php`, `src/Path/WorkingProjectPathResolver.php` |
| php-fast-forward/changelog | `e75a9be67114b13e593049ea3a37921259f295f7` | `AGENTS.md`, `docs/automation-map.md`, workflows and resource caller |
| coisa/php-cs-fixer | `fdee71bc63d4846463d7715afbc515d63ef2c537` | `src/Config.php`, `Finder.php`, `HeaderExtractor.php`, `RuleSet/DefaultSet.php`, `check-php.yml` |
| coisa/error-handler | `d5aa900b186b273d5ece8fe3a2f5b1c416a2d23b` | `.github/workflows/php-style.yml` |
| mentordosnerds/cli | `ad7651c26ecc6966b6a407c4c301ebedeccc11d1` | `.github/workflows/php-style.yml` |

The design reference is [DevTools #379](https://github.com/php-fast-forward/dev-tools/issues/379).
These are inspected local snapshots, not claims that local branches equal remote
main. Unrelated edits in source repositories were preserved. No implementation
from blocked PR #376 or composer-installers #15 was executed or changed.

| Policy | DevTools | CoiSA | Initial Code Standards |
| --- | --- | --- | --- |
| Engine | ECS plus separate PHPDoc fixer and Rector | PHP-CS-Fixer | ECS only; no competing pass |
| Named presets | Symfony + risky + auto + risky; prepared PSR12/common/Symplify/cleanCode | PhpCsFixer + risky + auto + risky PHP migration | Prepared PSR12 only, plus left PHPDoc alignment |
| Runtime | PHP 8.3+ contract | PHP ^8.3 | PHP ^8.3 |
| Consumer PHP target | Rector resolves Composer minimum | auto rules resolve minimum | No version-dependent migration sets |
| PHPDoc preservation | skips conversion, empty/redundant tags removal, global imports, general annotation removal | removes author; preserves empty return/package | Same six ECS skips as DevTools; left alignment |
| Additional behavior | PHPUnit static calls self; missing typed params; refactoring sets | immutable date, private visibility, simplified null returns and migration | Deliberately absent pending separate profile/fixtures |
| Headers | `.docheader`, current year, after strict declaration | opening header extracted without executing config, updated year, after open | No automatic header rewrite |
| Scope | source resolver excludes generated/resources/public/vendor | consumer Finder, hidden files, VCS ignores, path exclusions | Tracked PHP/bin scope, same generated exclusions plus node_modules; symlinks rejected |
| Overrides | customize callback last | extraRules last | customize callback last; explicit config selection |
| Check | combined orchestration has independent mutation risks | dry-run fixer | syntax parse then ECS check, no source writes |
| Semantic transformations | Rector seven sets, PHP migration, Safe migration, Faker and custom PHPDoc | mixed into risky fixer | Not implemented; requires explicit target, opt-in and independent tests |

The explicit rule deltas above are intentional. Transitive upstream preset
membership is dependency-version specific and fixed for contributor builds by
`composer.lock`.
ECS 13.3.8 deprecates `withPhpCsFixerSets()` as a no-op. The initial implementation
therefore uses the supported prepared PSR-12 set and proves violations with a
real fixture instead of inheriting the legacy dynamic-set call blindly.

The [resolved initial preset](resolved-preset.md) records the compiled ECS rule
list and effective options for the locked version. Golden fixtures make style,
documentation/semantic preservation and second-pass stability reviewable. This
is a first bounded part of #5, not equivalence with the legacy engines.

The [resolved legacy fixer comparison](legacy-fixer-comparison.md) expands all
three actual fixer profiles with normalized options, reported risk, priority
and source hashes. It distinguishes bundled code from root Composer metadata
and keeps the remaining full-pipeline decisions explicit.

This is not a rule-by-rule equivalence proof for the complete
legacy ECS + Rector + PHPDoc stack. No consumers may be automatically migrated
on the strength of this comparison. Before migration, expand resolved rule lists,
exercise headers and overrides in golden fixtures and decide which semantic
transformations belong in an opt-in profile. Optional Twig/magic-value policy,
Composer metadata normalization and existing public DevTools APIs stay untouched.

Static quality in this first increment means parseable PHP and formatting
conformance. Type analysis, dead-code analysis and semantic refactors are not
claimed. Application tests, coverage and complete reports remain outside this domain.

The separately opted-in [native Rector command](rector.md) selects no legacy
semantic profile and is never invoked by
formatting check/fix; the consumer explicitly owns rules, target PHP and caches.

The [PHPDoc comparison](phpdoc-comparison.md) records actual custom Rector
activation and the fixtures required before adopting a documentation policy.
The [static-analysis evaluation](static-analysis-evaluation.md) distinguishes
candidate engines and their analysis scopes without deciding domain ownership.
