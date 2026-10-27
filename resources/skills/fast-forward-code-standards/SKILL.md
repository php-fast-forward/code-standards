---
name: fast-forward-code-standards
description: Check or explicitly fix PHP formatting and syntax using Fast Forward Code Standards, including changed-file PR workflow adoption and review of local ECS overrides.
license: MIT
compatibility: Check/fix baseline fast-forward/code-standards 0.3.2, PHP ^8.3 and Composer runtime API ^2.2. Qualify optional commands against the installed CLI help.
metadata:
  api-baseline: fast-forward/code-standards 0.3.2 (check/fix)
  repository: https://github.com/php-fast-forward/code-standards
---

# Fast Forward Code Standards

The check/fix baseline is `fast-forward/code-standards` 0.3.2 with PHP `^8.3`
and Composer runtime API `^2.2`. Earlier releases are outside this skill's
validated baseline. Inspect the installed version and help before choosing a route;
this baseline is not an instruction to change a consumer's dependency constraint.

## Ordinary check/fix route

This entrypoint is sufficient when only the skill payload is available. Repository
guides are optional references, not prerequisites for ordinary formatting.
When installation is requested and the package is absent, use normal Composer
resolution; preserve an existing consumer constraint instead of silently upgrading:

```sh
composer require --dev fast-forward/code-standards
composer show fast-forward/code-standards
vendor/bin/code-standards --help
vendor/bin/code-standards check --all
```

Run from the consumer's Git checkout root. A check selects tracked PHP files,
parses their syntax and reports ECS violations without changing source. Exit 0
means the selected checks passed; a nonzero status requires inspecting diagnostics,
not assuming every failure is a formatting difference. The formatter's executing
PHP version does not certify the consumer's minimum supported PHP version.

For an explicitly requested local fix:

```sh
vendor/bin/code-standards fix --all
git diff --check
git diff
vendor/bin/code-standards check --all
```

Fix may be partial on failure. Preserve unrelated edits, review changed syntax
against the consumer's PHP requirements and run affected tests before accepting it.
If the dependency graph cannot support the tool runtime, stop instead of changing
the consumer's PHP platform or dependency constraints implicitly.

## Configuration and optional routes

1. Read consumer AGENTS, Git status, PHP requirements and existing style config.
   Preserve local edits. Do not replace another preset or migrate consumers
   without an explicit request and a fixture-backed policy comparison.
2. Select a published version or reviewed commit and inspect its installed version
   and CLI `help`; never invent a version. Inspect that ref's workflow when CI
   adoption is requested. A verified GitHub v0 channel
   is the selected workflow policy;
   the runtime stays bound to the actual executing workflow commit. GitHub v0
   is not a Composer/CPX version constraint.
3. Use `code-standards check --all` for a full tracked scan. For a clean PR head,
   use `check --base=<immutable-base> --head=<immutable-head>`. Check means syntax
   and ECS style only; it does not prove type safety, tests or coverage.
4. Select a local ECS configuration explicitly with `--config=ecs.php` and keep
   imports/overrides. Configuration is executable PHP: never load it with a
   privileged token or on a privileged target event. No incidental dependency update.
5. Only when fixing is requested run `code-standards fix --all`, inspect the diff
   and rerun check. Do not commit/push automatically. Semantic migrations need a
   separate reviewed procedure. First confirm that the selected CLI's `help`
   lists `refactor`; the 0.3.2 baseline has only check/fix. If absent, stop this
   route without silently upgrading the consumer. When available, `refactor` requires explicit
   native Rector configuration, an explicit consumer PHP target, source paths and
   reviewed rules. Native cache configuration is optional; Rector may use memory in CI.
   Preview is default; only explicit `--apply` grants semantic writes.
   Use only trusted, deterministic native configuration: preview runs one
   inventory and one processing invocation; successful apply adds verification.
   Rector can evaluate configuration repeatedly within each invocation. Changing
   targets or rules between evaluations can defeat preflight validation.
   Dry-run is not a sandbox: configuration, Composer autoload files, custom rules
   and bootstraps can execute code and mutate files. Never use these with secrets,
   privileged events or untrusted consumers. The child process does not isolate
   namespaces: audit class origins against the consumer checkout and qualify
   tool/consumer autoload/bootstrap compatibility; fail on collisions rather than
   accepting a false clean result. Review semantic diffs and run affected consumer
   tests; zero status alone does not prove semantic safety. The same ref's
   `docs/rector.rst` is an optional detailed guide. Never substitute this command
   for ordinary formatting `fix` or automatically migrate consumers.
6. For requested CI adoption copy `resources/workflows/code-standards.yml` from
   the selected ref after v0 points to a confirmed 0.x release, and use `pull_request` with
   read-only permissions and a hosted runner. No secrets/inherit, write token,
   target event or automatic fix. Required-check settings remain separately owned.

This directory is the sole source payload. Installation/host activation is explicit;
copying this skill does not install the runtime or authorize consumer rollout.
LLM Skills owns discovery/install/update; follow the selected ref's distribution
contract rather than adding another copy/symlink installer in this tool.
