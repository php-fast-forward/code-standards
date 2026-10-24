# Fast Forward Code Standards

Focused PHP style and static syntax checks, independent of DevTools. The library
provides an extendable ECS PSR-12 preset; the CLI checks tracked PHP files or the files
changed by an exact PR head. This initial increment validates PHP syntax and
style, not type correctness, application behavior or coverage.

## Local development and first adoption

Requires PHP 8.3+ and Git. Composer resolves the tool runtime independently from
the consumer application's PHP minimum; syntax is checked using the executing
PHP binary. No PHP migration rules are enabled. Review compatibility with the
application's minimum PHP before adopting a preset.

```sh
composer install --no-plugins --no-scripts
php bin/code-standards help
php bin/code-standards check --all
php bin/code-standards check --base=BASE_COMMIT_SHA --head=HEAD_COMMIT_SHA
php bin/code-standards fix --all
composer test
```

`check` reports violations with a nonzero status and does not change source.
The default preset stores its ECS cache beneath the system temporary directory,
in `fast-forward-code-standards/<SHA-256 of the executing checkout path>`.
Different checkouts cannot invalidate each other's cache. Native
`withCache()` overrides remain supported and apply last; share cache directories
only when their invalidation and concurrent use are intentionally coordinated.
`fix` explicitly applies ECS fixes. It does not commit, push or update dependencies.
Recheck and inspect the diff afterward; failures can leave already applied fixes.
An empty PHP selection is a successful no-op. Invalid options, configuration paths,
Git history or head mismatch fail closed with exit 2. Syntax/style tool failures,
including executable configuration errors inside ECS, retain their tool status.
Changed mode requires a clean tracked checkout of the requested head.
`--all` includes tracked files and permits inspecting local tracked edits.
Symfony Console provides `help check`, standard global flags and both
`--option=value` and `--option value`. Error diagnostics use stderr. Empty paths,
conflicting scan modes and unsupported arguments are rejected explicitly.

### Library and local configuration

Once a reviewed version/ref is available, install `fast-forward/code-standards`
as development tooling. No release of this package is assumed by these examples.
To trial before publication, use a Composer path repository pointing at this
checkout, require `*@dev`, and disable plugins/scripts. Production adoption must
select the reviewed Composer version and workflow/runtime refs together.

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

## File scope and guarantees

- Tracked `*.php` and PHP entrypoints under `bin/` are included.
- `vendor`, `.git`, `.build`, `.dev-tools`, `backup`, `cache`, `public`,
  `resources`, `tmp`, `node_modules` directory components are excluded.
- Candidate PHP/bin symlink paths fail closed; unrelated non-PHP aliases are ignored.
  Spaces, tabs, newlines and leading dashes remain
  individual subprocess arguments; deleted files are omitted, renames use destinations.
- Changed mode uses `base...head`, so unrelated base-branch changes are excluded.
  Composer, ECS and EditorConfig changes escalate to a complete tracked scan.
- Tools run in bounded batches and stop on failure. Syntax checks use `php -n -l`,
  so selected application sources are parsed without executing them.

See [policy and differences](docs/policy.md), [workflow/ref adoption](docs/automation.md)
and the [consumer skill](resources/skills/fast-forward-code-standards/SKILL.md).
The [architecture decision](docs/architecture.md) defines library/tool dependency
directions and CPX/autoload limits.
Rector/custom PHPDoc transformations and deeper static analysis are separate,
unimplemented increments; this package does not assert extraction parity yet.
