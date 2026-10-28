# Native configuration and PR checks

Load when adapting consumer policy or adding a PR workflow. Preserve existing
configuration; do not replace another preset without a reviewed comparison.

## Native ECS overrides

`--config=ecs.php` selects an existing PHP file relative to `--directory` or the
current Git root. The packaged preset is used otherwise. Its optional native
builder callback runs last, so explicit imports, skips and configured rules win.
For a consumer deliberately choosing vertical PHPDoc tag alignment, save `ecs.php`:

```php
<?php

declare(strict_types=1);

use FastForward\CodeStandards\Config\ECSConfig;
use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
use Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder;

return ECSConfig::configure(
    static function (ECSConfigBuilder $config): void {
        $config->withConfiguredRule(PhpdocAlignFixer::class, ['align' => 'vertical']);
    },
);
```

```sh
vendor/bin/code-standards check --all --config=ecs.php
# Only with explicit fix authority:
vendor/bin/code-standards fix --all --config=ecs.php
vendor/bin/code-standards check --all --config=ecs.php
```

The worker loads its own installation autoloader; this file needs no consumer
`require vendor/autoload.php`. Do not casually combine tool/consumer autoloaders
or use ECS's prefixed internals. Configuration/imports/extensions are executable
PHP, not sandboxed data. Use reviewed code without privileged tokens, secrets
or privileged target events. Default caches are temporary and checkout-isolated;
native overrides remain consumer-owned. Keep caches outside source and unique
to the checkout. Protect meaningful tags, descriptions and inherited PHPDoc;
do not enable risky migrations just to obtain a clean formatting result.

## Selection boundaries

Choose exactly `--all` or the immutable `--base`/`--head` pair. `--directory` (`-C`)
selects the Git root. Missing Git/history, wrong root, mismatched HEAD, dirty
tracked changed-mode state, missing config and escaping/symlink candidates fail.
A standalone non-Git directory is not a supported root.

Root/nested `.gitignore` rules apply dynamically, including anchors, escapes
and negations. Global ignores and `.git/info/exclude` do not change this
project-owned policy. `.git` metadata is structurally excluded; `public` and
`resources` are eligible unless ignored. Composer/ECS/EditorConfig and ignore
policy changes can invalidate changed-only selection. Paths use Git's NUL
protocol; never split them on whitespace or rebuild an executable shell string.

## Read-only reusable workflow

Inspect the selected ref's `resources/workflows/code-standards.yml` and called
workflow before adopting this wrapper:

```yaml
name: PHP standards
on:
  pull_request:
permissions:
  contents: read
jobs:
  standards:
    uses: php-fast-forward/code-standards/.github/workflows/code-standards.yml@v0
    with:
      php-version: '8.3'
```

Use `v0` only when it points to a confirmed published 0.x release; a reviewed
immutable workflow commit is another explicit policy. This GitHub channel is
not a Composer/CPX constraint. Runtime/helper source stays bound to the actual
called workflow repository/SHA, never a second moving-ref lookup. Use only inputs
supported by that ref.

The reusable accepts `normalize-composer: true` to check the consumer root's
manifest and optional lock without writing, independently of changed PHP files.
It defaults to `false`; missing or invalid metadata fails when explicitly enabled.
This project's own metadata gate does not opt other consumers in.

Keep `pull_request` and read-only contents. Hosted Ubuntu remains the default.
The optional `runs-on` string accepts JSON labels for an existing authorized
runner only for trusted same-repository PRs; forks always select hosted Ubuntu
before scheduling. A pre-checkout guard additionally rejects foreign heads on
self-hosted runners. This is not a sandbox for untrusted code or a runner-access
change. Check the selected release's inputs and runner requirements before use.
No secrets/inherit, privileged target events, implicit plugin installation or
automatic fixes.
Required-check settings and rollout across consumers are separately authorized.

## Multiline conditions

The preset uses native PSR-12 wrapper layout for braced multiline `if`, `elseif`,
`while` and `do-while` conditions, with PER 3.0 leading boolean operators. An owned
whitespace rule additionally puts each symbolic `&&`/`||` on its own line inside
those conditions, including grouped expressions, arguments and indexes. Function,
arrow and anonymous-class declarations remain opaque; single-line conditions
retain native policy. The operator-per-line requirement is a project extension.

The default uses four spaces/LF. Native `withSpacing()` overrides for two spaces,
tabs or CRLF remain explicit deviations from PSR/PER. A consumer can select native
`withSkip()` entries for `MultilineBooleanConditionSniff` and
`MultilineConditionSpacingSniff`; preserve other intentional skips because that
method replaces the list. The default temporary cache includes the installed
preset/checker paths and bytes, so an owned-rule change cannot reuse an older
passing result. Explicit cache overrides remain authoritative.
