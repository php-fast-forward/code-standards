---
name: fast-forward-code-standards
description: Use Fast Forward Code Standards to check PHP syntax and formatting, apply an explicitly requested style fix, configure native ECS overrides, preview opt-in Rector changes, analyze magic numbers, or adopt its changed-file PR workflow. Use fast-forward-phpdoc for source-documentation authoring instead.
license: MIT
compatibility: Supports fast-forward/code-standards ^0.5.0, PHP ^8.3, Git and the PHP extensions declared by the installed package (including Phar and zlib). Native XML magic-number reports additionally require ext-dom. Inspect the installed version and help; do not silently upgrade an incompatible consumer.
metadata:
  api-baseline: fast-forward/code-standards ^0.5.0
  repository: https://github.com/php-fast-forward/code-standards
---

# Fast Forward Code Standards

Work from the consumer's Git root. Read its instructions, Git status, PHP
requirements and style configuration first. Preserve local work and constraints.
This skill supports the published `^0.5.0` command contract; inspect compatible
source/help for an older installation rather than upgrading it implicitly.
Executing on the tool's PHP does not certify the consumer's minimum PHP.

## Check first

If installation is requested and the package is absent, use normal Composer
resolution. Merely requesting a check does not authorize manifest/lock changes.

```sh
composer require --dev fast-forward/code-standards
composer show fast-forward/code-standards
vendor/bin/code-standards list
vendor/bin/code-standards help check
vendor/bin/code-standards check --all
```

Composer installs the compiled `builds/code-standards` executable and creates
`vendor/bin/code-standards`; consumers do not build it or install its internal
libraries. Adopt only a published version whose distribution contains that artifact.
A source merge alone is insufficient. Optional Composer scripts can call
`code-standards check --all` and `code-standards fix --all`; only invoke the latter
when a fix is explicitly requested. Global use follows `composer global require
fast-forward/code-standards` and Composer's global bin directory.
No config file is required for the packaged PER Coding Style 3.0 preset. `--all`
selects tracked PHP files and PHP entrypoints under `bin/`, excluding matches of
the consumer's root/nested `.gitignore`, including force-added tracked matches.
Untracked files and staged-only contents are outside this contract; tracked
working-tree edits are allowed. Syntax runs before ECS. Check never passes
`--fix` or updates dependencies and requests no source writes; tool caches may
use temporary storage. Executable consumer extensions have the trust boundary
described in the configuration reference. Check does not run tests, coverage,
PHPStan, Rector or magic-number analysis.

Exit `0` means selected checks passed or selection was empty. Inspect diagnostics
on any nonzero exit: invalid settings/history/configuration generally return `2`,
while native syntax/style failures retain their tool status. Status alone does
not distinguish invalid input from formatting findings.

For PR scope, obtain its actual immutable 40-character base/head SHAs:

```sh
vendor/bin/code-standards check --base="$BASE_SHA" --head="$HEAD_SHA"
```

The tracked checkout must be clean and HEAD must equal `$HEAD_SHA`. Selection
uses the merge base and handles renames/deletions without losing path boundaries.
Policy/config changes can trigger a full eligible scan. Do not substitute moving
branch names or treat this as an index/staged-blob check.

## Apply only the requested operation

An explicit formatting request permits:

```sh
vendor/bin/code-standards fix --all
git diff --check
git diff
vendor/bin/code-standards check --all
```

Fix can be partial on failure. Inspect every diff, preserve unrelated edits and
run affected consumer tests. Do not commit/push without authority. Fix does not
imply semantic refactoring or changes to PHP requirements, dependencies or
manual configuration.

Load only the reference needed for the task:

- [Configuration and PR automation](references/configuration-and-ci.md): native
  ECS overrides, file-selection failures and read-only workflow adoption.
- [Optional tools](references/optional-tools.md): complete Rector preview/apply
  and PHPMND text/XML routes, statuses and execution limits.
- [Skill installation](references/installation.md): optional project discovery,
  trust, copies, updates and removal. Loading this payload installs no runtime,
  activates no host and authorizes no consumer rollout.

For PHPDoc authoring/review select `fast-forward-phpdoc`. Formatter success cannot
prove a documented semantic promise is true.
