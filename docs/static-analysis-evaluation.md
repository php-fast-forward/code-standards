# Static analysis: candidates and ownership boundary

This source comparison records candidates without deciding ownership or
implementing an analyzer. DevTools issues
[#14](https://github.com/php-fast-forward/dev-tools/issues/14),
[#265](https://github.com/php-fast-forward/dev-tools/issues/265),
[#266](https://github.com/php-fast-forward/dev-tools/issues/266) and
[#267](https://github.com/php-fast-forward/dev-tools/issues/267) remain open.
These decisions are not prerequisites for the initial 0.1.0 style/syntax profile.

## Existing source

Inspected DevTools `35b5f78ac63813ec07aa3fb3eda08751541e28e1`. Bounded searches of
composer.json, src/, resources/, .github/ and README found no declarations of
PHPStan/Psalm/PHPat/php-assumptions/TypePerfect/security-audit engines or a
dedicated command for these candidate source analyzers. Existing Dependencies
and Metrics commands serve other contracts. PHPStan/Psalm filenames occur in
GitAttributes CandidateProvider only.
This is evidence of the searched surfaces, not a proof about every historical
branch or generated dependency. No source file was changed or scan executed.

## Actual candidate engines

Moving upstream default-branch manifests read via the authorized GitHub connector
on 2026-10-21 at 21:12 UTC, with exact file blob hashes below. These are not selected published
package versions or verified runtime compatibility claims.

| Source issue | Candidate and real upstream | Observed contract | Consequence |
| --- | --- | --- | --- |
| DevTools14 | phpstan/phpstan | Dedicated analyzer/PHAR; native analyse/configuration/level/error-format/memory-limit | Prefer native settings and exits; no generic multi-engine abstraction before a second real requirement |
| DevTools265 | rskuipers/php-assumptions | Standalone bin/phpa; php-parser4/5 + league/climate; README calls it a proof of concept | Not a PHPStan extension or runtime assertion framework on this evidence; identify intended package/version and test actual exits/target syntax before integration |
| DevTools266 | carlosas/phpat, package phpat/phpat | type phpstan-extension; PHP^8.1, PHPStan^2.1; extension.neon registered in extra | Real PHPStan extension; its development Psalm dependency is not a consumer runtime engine |
| DevTools267 | FloeDesignTechnologies/phpcs-security-audit, package pheromone/phpcs-security-audit | type phpcodesniffer-standard; PHP_CodeSniffer^3.1.0 + DealerDirect installer plugin; manifest declares GPL-3.0-or-later | Premise that this is a PHPStan security extension is incorrect; assess separately, without automatic plugin activation, mandatory adoption or inferred compatibility with modern PHPCS |

Manifest blob hashes: PHPStan `a267d4c50d8cd59cd63866d4c8b04a25f0a24627`;
assumptions `6037a1c9b39737581f74577f513d18333295354f`;
PHPat `055245974b55aa70c3a59954d373dc95c938b8e9`;
security-audit `a2a72a7b98d94d0e65e151f470dcc5715d9306a0`.
Security-audit README blob `63be29bacf78614110c556dfad68eafda4b140c4`
explicitly describes PHPCS invocation and significant noise; no scanner was run.

Primary manifests:
[PHPStan](https://github.com/phpstan/phpstan/blob/2.3.x/composer.json),
[assumptions](https://github.com/rskuipers/php-assumptions/blob/master/composer.json),
[PHPat](https://github.com/carlosas/phpat/blob/main/composer.json),
[security-audit](https://github.com/FloeDesignTechnologies/phpcs-security-audit/blob/master/composer.json).

## Changed-file semantics need a distinct decision

PHPStan explicitly recommends a stable whole-project analysed path set and uses
its native result cache/dependency tree to revisit dependents of changed symbols.
Therefore the ECS changed-file list must not be presented as equivalent type
analysis. A changed method signature can invalidate unchanged callers; checking
only the changed source would not establish those callers' validity. This last
example is an architectural inference, not an executed regression result.
If a restricted mode is retained later, name its limits explicitly and require
cross-file dependency regressions; native whole-project analysis is the proposed
default for an eventual PHPStan slice. No new CLI option was implemented.
[Official result-cache contract](https://phpstan.org/user-guide/result-cache).

PHPStan resolves symbols from analysed paths and the consumer Composer graph;
custom bootstrap/autoload PHP actually executes. A separately installed tool
does not remove that execution or namespace boundary. Preserve consumer NEON,
imports and baselines; use explicit target-PHP/config selection, unprivileged
processes, isolated tool cache and no incidental Composer scripts/plugins or
baseline writes. Test that checkout symbols win over an installed library with
the same namespace before adoption.
[Symbol discovery](https://phpstan.org/user-guide/discovering-symbols),
[native CLI](https://phpstan.org/user-guide/command-line-usage).

## Remaining gates

- Decide Code Standards ownership versus another tool before closing or moving
  the four DevTools candidates. Do not implement every optional engine at once.
- Select actual published versions, inspect licenses/compatibility and prove
  execution/format/exit/read-only behavior with synthetic cross-file fixtures.
- Keep TypePerfect semantic changes opt-in and independently scoped; its actual
  package/groups remain to verify rather than copying an issue's assumption.
- Preserve current first-release boundary: syntax + non-risky ECS style only;
  tests/coverage/full reporting remain other domains. No consumer migration.
