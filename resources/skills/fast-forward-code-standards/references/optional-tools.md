# Optional semantic refactoring and magic-number analysis

Load only for a requested operation. Confirm installed `help refactor` or
`help magic-numbers`; neither command runs as part of ordinary check/fix.

## Rector preview and explicit apply

Save a reviewed native `rector.php` in the consumer root. Deliberately select its
PHP target, paths and rules; this example targets PHP 8.3 and only shortens arrays:

```php
<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPaths([__DIR__ . '/src'])
    ->withRules([LongArrayToShortArrayRector::class])
    ->withoutParallel();
```

```sh
vendor/bin/code-standards refactor --config=rector.php
# After reviewing the preview and receiving apply authority:
vendor/bin/code-standards refactor --config=rector.php --apply
git diff --check
git diff
vendor/bin/code-standards refactor --config=rector.php
```

Preview is native dry-run. An explicit target and nonempty rules are required;
Composer/platform fallback does not supply that target. The configuration owns
whole-project paths; PR changed-file selection is not used. `--directory` selects
the root; `--timeout` defaults to `300` positive whole seconds per subprocess,
including preflight and apply verification. Preserve native cache, bootstrap and
parallel settings. Keep any file cache checkout-specific and outside source;
Rector may use memory storage in CI.

Native exits: `0` clean/success, `2` proposed changes, `1` processing errors or
timeouts. Controlled settings/config/inventory failures also return `2`; other
native fatal exits propagate. Inspect diagnostics instead of interpreting `2`
blindly. Apply writes then verifies by dry-run, so remaining differences fail.
Review API/side-effect changes and run affected tests: zero does not prove safety.

Use trusted deterministic configuration. Preview invokes inventory and processing;
successful apply adds verification. Rector may evaluate config repeatedly within
each invocation; changing targets/rules can defeat preflight. Config, autoloads,
rules and bootstraps can execute/mutate even during dry-run. A child process is
not a sandbox or namespace isolation: qualify class origins and consumer/tool
bootstrap compatibility; fail on collisions rather than accepting a false clean
result. Never run these extensions with privileged events/secrets or an untrusted
consumer. Formatting fix is not permission to apply semantic changes.

## PHPMND analysis

```sh
vendor/bin/code-standards magic-numbers --all
vendor/bin/code-standards magic-numbers --all --format=xml > magic-numbers.xml
```

The same immutable base/head selection and `--directory` are supported. No ECS
config or consumer autoload/config is implicitly loaded. Analysis reports
literals; it does not invent names, equate constants by value or apply fixes.

Defaults inspect conditions, returns and switch cases, ignoring `0` and `1`.
`--ignore-numbers=42` adds an exception; empty does not remove native zero/one
exceptions. `--extensions=argument` adds an AST context, not a file suffix;
`--extensions=all,-argument` selects all except arguments. `--ignore-funcs=output`
adds an exception only when argument analysis is enabled, without enabling it.
These broad overrides are not a per-finding accepted-debt baseline.

Text is native default output. XML requires `ext-dom`, writes one native document
to stdout and diagnostics to stderr. Paths and complete selected contents must
be valid UTF-8/XML 1.0; unsupported bytes fail before analysis/output. Absolute
paths make XML location-dependent. Empty selection does not run the engine;
missing DOM still fails in XML mode. Argument bytes are limited to 64 KiB;
oversized selections fail without dropping files. Concatenating XML documents
is not a supported batching strategy.

Exit `0` means no findings or empty selection. Native `1` may mean findings or
engine failure; invalid settings/scope/process exceptions return `2`, which may
also be native. Other nonzero statuses propagate. Inspect the report and stderr,
preserve status and never convert operational failure into a clean pass with
blanket `continue-on-error` or `|| true`.
