# Fast Forward Code Standards

![Dash, the Fast Forward fox, aligning source cards to illustrate consistent code style.](docs/_static/mascot-banner.png)
[![PHP](https://img.shields.io/badge/php-%5E8.3-777BB4?logo=php&logoColor=white)](composer.json)
[![Composer Package](https://img.shields.io/badge/composer-fast--forward%2Fcode--standards-F28D1A?logo=composer&logoColor=white)](https://packagist.org/packages/fast-forward/code-standards)
[![Tests](https://img.shields.io/github/actions/workflow/status/php-fast-forward/code-standards/repository-ci.yml?branch=main&label=tests)](https://github.com/php-fast-forward/code-standards/actions/workflows/repository-ci.yml)
[![Docs](https://img.shields.io/badge/docs-source-38BDF8)](docs/architecture.md)
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
- An unprivileged reusable workflow and a portable consumption skill.

## 📦 Installation and requirements

Install the published development tool in your Git project:

```sh
composer require --dev fast-forward/code-standards
vendor/bin/code-standards check --all
```

The GitHub `v0` workflow channel and the Composer package are separate
distribution mechanisms.

Requires PHP 8.3 or later in the 8.x series, Git and Composer runtime API 2.2+
for installed binary proxies. Composer resolves the tool with the project's
dependency constraints; syntax is checked using the executing PHP binary.
No PHP migration rules are enabled. Review compatibility with the
application's minimum PHP before adopting a preset.

Workflow guards and their contributor tests additionally require Bash and jq.
Hosted Ubuntu runners provide both; the Composer library and CLI use the PHP
dependencies declared in this package.

Composer exposes the portable skill through `extra.skills.source` at
`resources/skills`. `llm/skills` is an optional consumer integration; installation
and activation are explicit. The development `check` script validates Composer
metadata and the actual platform before running this package's tests and style
check.

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
The default preset stores its ECS cache beneath the system temporary directory,
in `fast-forward-code-standards/<SHA-256 of the executing checkout path>`.
Different checkouts cannot invalidate each other's cache. Native
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

require __DIR__ . '/vendor/autoload.php';

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
both ECS and a competing PHP-CS-Fixer pass. Standalone ECS users may import the
same library preset and set their own paths. CLI selection uses the scope below;
ECS's configured paths do not expand the CLI's explicit selected file list.

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

See [policy and differences](docs/policy.md), [workflow/ref adoption](docs/automation.md)
and the [consumer skill](resources/skills/fast-forward-code-standards/SKILL.md).
The [architecture decision](docs/architecture.md) defines library/tool dependency
directions and CPX/autoload limits.
The formatter preserves descriptive PHPDoc and inherited documentation.
The separate opt-in [`refactor` command](docs/rector.md) previews an explicit
native Rector configuration and requires `--apply` for semantic writes. It is
never invoked by formatting `check` or `fix`; target PHP and cache remain native
consumer configuration decisions. Custom PHPDoc transformations and deeper
static analysis remain separate; no DevTools extraction parity is asserted.

## 🧰 API summary

| Entry point | Contract |
| --- | --- |
| `code-standards check` | Parse selected PHP and check style without changing source. |
| `code-standards fix` | Explicitly format selected source, then verify; failures may leave partial changes. |
| `code-standards refactor --config=rector.php` | Preview native semantic rules; only `--apply` permits writes, followed by verification. |
| [`code-standards magic-numbers`](docs/magic-numbers.rst) | Analyze selected source with opt-in PHPMND, independently of check/fix; findings keep a nonzero status. |
| `ECSConfig::configure(?callable $customize)` | Return the native ECS builder; apply the optional callback last. |
| `help check` / `help fix` / `help refactor` / `help magic-numbers` | Show the installed Symfony option definitions. |

Internal CLI services are implementation details. Supported configuration uses
native ECS rule imports, skips and configured rules; there is no second DSL.

## 📁 Package layout

```text
bin/code-standards                       CLI entrypoint
config/ecs.php                          default native ECS configuration
src/Config/ECSConfig.php                 reusable preset API
resources/skills/fast-forward-code-standards/  canonical consumer skill
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
- [Automation and consumer refs](docs/automation.md)
- [Policy and differences](docs/policy.md)
- [Fast Forward Container](https://github.com/php-fast-forward/container)
- [Changelog](https://github.com/php-fast-forward/changelog)
