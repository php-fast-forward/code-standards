# Fast Forward Code Standards

![Dash, the Fast Forward fox, aligning source cards to illustrate consistent code style.](docs/_static/mascot-banner.png)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777BB4?logo=php&logoColor=white)](composer.json)
[![Composer Package](https://img.shields.io/badge/composer-fast--forward%2Fcode--standards-F28D1A?logo=composer&logoColor=white)](https://packagist.org/packages/fast-forward/code-standards)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-fast-forward/code-standards/repository-ci.yml?branch=main&label=tests)](https://github.com/php-fast-forward/code-standards/actions/workflows/repository-ci.yml)
[![Docs](https://img.shields.io/badge/docs-source-38BDF8)](docs/architecture.rst)
[![License](https://img.shields.io/github/license/php-fast-forward/code-standards?color=64748B)](LICENSE)
[![GitHub Sponsors](https://img.shields.io/github/sponsors/php-fast-forward?color=EC4899)](https://github.com/sponsors/php-fast-forward)

Focused PHP style and static syntax checks, independent of DevTools. The library
provides an extendable ECS PER Coding Style 3.0 preset; the CLI checks tracked PHP
files or the files changed by an exact PR head. It validates PHP syntax and style;
type analysis, application tests and coverage belong to their respective tools.

## ✨ Features

- Native ECS configuration with consumer overrides applied last.
- Read-only syntax/style checking and explicitly requested local fixes.
- Exact PR merge-base selection with binary-safe file paths.
- Invocable Symfony Console commands composed with Fast Forward Container.
- An unprivileged reusable workflow with explicit optional analysis summaries and a portable consumption skill.

## 📦 Installation and requirements

Install the published development tool in your Git project:

```sh
composer require --dev fast-forward/code-standards
vendor/bin/code-standards check --all
```

The compiled executable is installed directly through Composer's binary proxy.
Optional scripts keep checks convenient without building the tool in consumers:

```json
{"scripts":{"standards:check":"code-standards check --all","standards:fix":"code-standards fix --all"}}
```

Run `composer standards:check`; apply formatting explicitly with
`composer standards:fix`. Global installation uses
`composer global require fast-forward/code-standards`, then the binary in
`composer global config bin-dir --absolute`.

The GitHub `v0` workflow channel and the Composer package are separate
publications. See the [PHAR distribution contract](docs/phar-distribution.rst)
for the pending artifact publication gate; a source merge does not publish a ready package.
Copyable workflow callers live in [resources/workflows](resources/workflows/README.md).

Requires PHP ^8.3, Git and the PHP extensions declared in `composer.json`,
including Phar and zlib. Bundled libraries do not enter the consumer's dependency
graph. Executable configurations may load consumer classes; this is no namespace sandbox.
Syntax uses the executing PHP binary. No PHP migration rules are enabled.
Workflow guards and their contributor tests additionally require Bash and jq.
Hosted Ubuntu runners provide both; the Composer library and CLI use the PHP
dependencies declared in this package.

Composer exposes the portable skills through `extra.skills.source` at
`resources/skills`. `llm/skills` is an optional consumer integration; installation
and activation are explicit. The development `check` script validates Composer
metadata and the actual platform before running this package's tests and style
check.

Use the [Code Standards skill](resources/skills/fast-forward-code-standards/SKILL.md)
for tool adoption and explicit formatting. Use the dedicated
[PHPDoc skill](resources/skills/fast-forward-phpdoc/SKILL.md) to write or review
concise summaries, semantic contracts, useful tags and inherited documentation.
Each payload includes its license and relative references; neither activates an
agent or installs another tool automatically.
See the [optional skill installation and lifecycle guide](resources/skills/fast-forward-code-standards/references/installation.md)
for project discovery, trust and update ownership.

For a reviewed source checkout:

```sh
composer install --no-plugins --no-scripts
php bin/code-standards help
php bin/code-standards check --all
php bin/code-standards check --base=BASE_COMMIT_SHA --head=HEAD_COMMIT_SHA
php bin/code-standards fix --all
composer test
```

## 🛠️ Usage

`check` reports violations with a nonzero status and does not change source.
Use `check --all --format=json` for an explicit single-document machine report,
including native ECS findings, failure classification and remaining-work counts.
See the [structured check contract](docs/structured-check.rst). Text is the
default; `fix` uses text output.
Use `--format=json-pretty` for the same report with indentation; see
[result presentation](docs/process-contracts.rst#structured-check-presentation).
The default preset stores its ECS cache beneath the system temporary directory,
in `fast-forward-code-standards/<SHA-256 of the executing checkout path>/<owned-policy fingerprint>`.
The fingerprint includes the installed preset and owned checker paths and source
bytes. Different checkouts cannot invalidate each other's cache, and a changed
owned rule cannot reuse an earlier passing result. Native
`withCache()` overrides remain supported and apply last; share cache directories
only when their invalidation and concurrent use are intentionally coordinated.
`fix` explicitly applies ECS fixes. It does not commit, push or update dependencies.
After each successful formatter batch, a read-only check verifies the result.
Any remaining violation or tool error is returned as a nonzero status. A formatter
pass can expose another rule, such as EOF normalization preceding opening-tag
spacing; inspect the partial diff before explicitly requesting another fix.
Recheck and inspect the diff afterward; failures can leave already applied fixes.
An empty PHP selection is a successful no-op. Invalid options, configuration paths,
Git history or head mismatch fail closed with exit 2. Syntax/style tool failures,
including executable configuration errors inside ECS, retain their tool status.
Changed mode requires a clean tracked checkout of the requested head.
`--all` includes tracked files and permits inspecting local tracked edits.
Symfony Console provides `help check`, standard global flags and both
`--option=value` and `--option value`. Error diagnostics use stderr. Empty paths,
conflicting scan modes and unsupported arguments are rejected explicitly.

## ⚙️ Configuration and extension

Install `fast-forward/code-standards` as development tooling. Select the Composer
package and workflow/runtime refs independently: Composer resolves package
dependencies, while the GitHub workflow uses its reviewed ref.

```php
<?php

declare(strict_types=1);

use FastForward\CodeStandards\Config\ECSConfig;
use Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder;

return ECSConfig::configure(static function (ECSConfigBuilder $config): void {
    // Local rule imports/overrides go last; review their semantic risk.
    $config->withConfiguredRule(
        \PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer::class,
        ['align' => 'left'],
    );
});
```

Select it explicitly with `--config=ecs.php`. PHP configuration is executable
code and belongs in an unprivileged environment. The CLI defaults to its own
preset, does not discover or overwrite consumer configuration, and never runs
both ECS and a competing PHP-CS-Fixer pass. The preset API is available inside
the compiled tool's selected configuration. The package does not expose these
classes through the consumer autoloader. CLI selection uses the scope below;
ECS's configured paths do not expand the CLI's explicit selected file list.

For braced multiline `if`, `elseif`, `while` and `do-while` conditions, the
native PSR-12 wrapper places the first expression on the next line and the
closing parenthesis on its own line (`) {`, or `);` for do-while). The native
leading-operator policy follows PER 3.0. An owned whitespace policy additionally
puts each symbolic `&&` and `||` on its own line in those multiline conditions.
The policy includes grouped expressions, call arguments
and array indexes within that condition; function, arrow and anonymous-class
declarations remain opaque. Single-line conditions and other expression contexts
retain their native rules. Splitting every operator, including nested expression
parts, is a project extension rather than an additional PER requirement.
The default is four spaces and LF. Native ECS `withSpacing()` overrides for two
spaces, tabs or CRLF remain supported as explicit deviations from PSR-12/PER,
not standards-compliant alternatives.
Consumers can explicitly skip `FastForward\CodeStandards\Sniff\MultilineBooleanConditionSniff`
through `withSkip()`; that native method replaces the skip list, so preserve any
other intended preservation skips. See [the resolved preset](docs/resolved-preset.rst).
The separate `MultilineConditionSpacingSniff` adapts the native wrapper to the
same spacing service. Alternative colon syntax is preserved; the wrapper tests
do not certify that syntax, or the complete preset, against every PSR/PER rule.

The formatter worker uses ECS's public factory and command APIs with this tool's
own Composer installation. An isolated tool does not implicitly load the target
checkout's `vendor/autoload.php`. Explicit PHP configuration retains the checkout
as `getcwd()`, keeps `__DIR__` and relative paths intact, and may deliberately
require consumer autoloaders or register custom checkers. When the tool is a
development dependency in the consumer's own Composer graph, that installation's
normal binary-proxy bootstrap remains intentional; it is not a separate import
of another checkout's dependency graph.

## 🔌 Integration and file scope

- Tracked `*.php` and PHP entrypoints under `bin/` are included.
- The consumer's root and nested `.gitignore` files dynamically exclude matching
  paths, including already tracked/force-added files. Git interprets patterns,
  anchors, escapes and negations; this tool has no replacement glob parser.
  Global ignores and `.git/info/exclude` do not alter this project-owned policy.
  Untracked files remain outside the CLI's candidate universe. Only `.git`
  metadata is structurally excluded, including worktree metadata files; names
  such as `public`, `resources` or `backup` are eligible unless the consumer
  ignores them. Containment and PHP symlink rejection still apply.
- Candidate PHP/bin symlink paths fail closed; unrelated non-PHP aliases are ignored.
  Spaces, tabs, newlines and leading dashes remain
  individual subprocess arguments; deleted files are omitted, renames use destinations.
- Changed mode uses `base...head`, so unrelated base-branch changes are excluded.
  Composer, ECS, EditorConfig and root/nested `.gitignore` changes escalate to a complete tracked scan.
- Tools run in bounded batches and stop on failure. Syntax checks use `php -n -l`,
  so selected application sources are parsed without executing them.

Start with the [usage guide](docs/index.rst). See [policy and differences](docs/policy.rst), [workflow/ref adoption](docs/automation.rst)
and the [consumer skill](resources/skills/fast-forward-code-standards/SKILL.md).
The [architecture decision](docs/architecture.rst) defines library/tool dependency
directions and executable-configuration limits.
The formatter preserves descriptive PHPDoc and inherited documentation.
The separate opt-in [`refactor` command](docs/rector.rst) previews an explicit
native Rector configuration and requires `--apply` for semantic writes. It is
never invoked by formatting `check` or `fix`; target PHP and cache remain native
consumer configuration decisions. Custom PHPDoc transformations and deeper
static analysis remain separate; no DevTools extraction parity is asserted.

The reusable workflow accepts `normalize-composer: true` to check the consumer's
root Composer metadata without writes (default: `false`). This project's own CI
enables the check. See [workflow behavior](docs/composer-normalization.rst).

The optional `runs-on` JSON input selects an existing Linux self-hosted runner for
same-repository data-only checks. Forks, executable ECS config and Rector use
hosted Ubuntu. See
[runner trust and requirements](docs/automation.rst).

## 🧰 API summary

The reusable keeps optional analyses disabled by default. Enable
`magic-numbers: true` or `rector: true` explicitly; Rector also requires
`rector-config`. Each tool's `*-policy` accepts `report` or `fail` for verified
findings. Operational failures always fail, including invalid/incomplete native
reports. A visible summary distinguishes disabled, not executed, clean, findings
and operational error, with native counts and available file/line information.
See [optional workflow analyses](docs/optional-analysis.rst) for inputs, limits,
trust boundaries and consumer examples. The selected workflow revision defines
its input contract independently of Composer package constraints.

| Entry point | Contract |
| --- | --- |
| `code-standards check` | Parse selected PHP and check style without changing source. |
| `code-standards fix` | Explicitly format selected source, then verify; failures may leave partial changes. |
| [`code-standards normalize-composer`](docs/composer-normalization.rst) | Check Composer metadata without writing; `--apply` changes only `composer.json` and reports stale lock hashes. |
| `code-standards refactor --config=rector.php` | Preview native semantic rules; only `--apply` permits writes, followed by verification. |
| [`code-standards magic-numbers`](docs/magic-numbers.rst) | Analyze selected source with opt-in PHPMND, independently of check/fix; findings keep a nonzero status. |
| `code-standards refactor --config=rector.php --format=json` | Emit native JSON for preview only; JSON cannot be combined with `--apply`. |
| `ECSConfig::configure(?callable $customize)` | Return the native ECS builder; apply the optional callback last. |
| `help check` / `help fix` / `help refactor` / `help magic-numbers` | Show the installed Symfony option definitions. |
| `help normalize-composer` | Show metadata normalization options and explicit write authority. |

Internal CLI services are implementation details. Supported configuration uses
native ECS rule imports, skips and configured rules; there is no second DSL.

## 📁 Package layout

```text
bin/code-standards                       CLI entrypoint
config/ecs.php                          default native ECS configuration
src/Config/ECSConfig.php                 reusable preset API
resources/skills/fast-forward-code-standards/  canonical consumer skill
resources/skills/fast-forward-phpdoc/     portable PHPDoc authoring skill
resources/workflows/code-standards.yml   consumer workflow wrapper
.github/actions/check/                  small check adapter
docs/                                   architecture, policy and integration guides
```

## 🛠️ Versioning and compatibility

Development releases stay in `0.x`. A compatible correction uses a patch;
functionality and breaking development changes use a minor. Changelog automation
prepares and publishes versions through reviewed PRs. Verify actual
[releases](https://github.com/php-fast-forward/code-standards/releases) before
selecting a Composer constraint. The GitHub `v0` channel follows verified
publications and can advance across development minors; it is not a
Composer package constraint.

## ❓ FAQ

### Optional magic-number analysis

Run `vendor/bin/code-standards magic-numbers --all` explicitly for PHPMND
analysis. Add `--format=xml` for its native machine-readable report. The command
uses the same tracked-file/Git ignore policy and does not run as part of
`check` or `fix`. See [options, status and adoption guidance](docs/magic-numbers.rst).

**Can I use this as a staged-only pre-commit check?**
The CLI selects working-tree files from Git, not staged index blobs. Changed
mode requires a clean tracked checkout and cannot certify staged-only content.

**Do formatting check/fix run PHPStan, Rector or my application's tests?**
No. Formatting checks syntax and style. The separate opt-in `refactor` command
uses an explicit native Rector configuration; it never runs through check/fix.
Type analysis and application testing remain separate scopes.

**Does a successful fix always mean the source is clean?**
The CLI verifies each formatted batch and reports remaining violations. Rules
can interact across passes; inspect the diff before explicitly fixing again.

**Where do agent skills come from?**
The canonical payload is under `resources/skills`, advertised by
`extra.skills.source`. Consumers may explicitly select the optional LLM Skills
manager. This package does not provide another installer, activate skills or
migrate consumer settings.

## 🛡️ License

[MIT](LICENSE). Dash artwork follows the PHP Fast Forward brand references;
the upstream [artwork provenance record](https://github.com/php-fast-forward/.github/blob/v0.3.0/docs/brand/artwork-rights.md)
records its generative origin and historical rights limits separately.

## 🤝 Contributing

Read [AGENTS.md](AGENTS.md), add a focused regression and a unique `.changelog`
fragment, then open a scoped PR. Preserve check's source/lock no-write guarantee.

```sh
composer install --no-plugins --no-scripts
composer check
```

Workflow contributor checks also need Bash and jq. Changes use normal current-head
CI and review gates; this guide does not authorize release, security-setting
changes or consumer rollout.

## 🔗 Links

- [Repository](https://github.com/php-fast-forward/code-standards)
- [Issues](https://github.com/php-fast-forward/code-standards/issues)
- [Packagist](https://packagist.org/packages/fast-forward/code-standards)
- [Composer package metadata](composer.json)
- [Automation and consumer refs](docs/automation.rst)
- [Policy and differences](docs/policy.rst)
- [Fast Forward Container](https://github.com/php-fast-forward/container)
- [Changelog](https://github.com/php-fast-forward/changelog)
