---
name: fast-forward-code-standards
description: Check or explicitly fix PHP formatting and syntax using Fast Forward Code Standards, including changed-file PR workflow adoption and review of local ECS overrides.
license: MIT
metadata:
  repository: https://github.com/php-fast-forward/code-standards
---

# Fast Forward Code Standards

1. Read consumer AGENTS, Git status, PHP requirements and existing style config.
   Preserve local edits. Do not replace another preset or migrate consumers
   without an explicit request and a fixture-backed policy comparison.
2. Inspect the selected reviewed release/ref's README, workflow and CLI `help`.
   Before the first release use a reviewed commit or local path trial, never an
   invented version. A verified GitHub v0 channel is the selected workflow policy;
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
   separate reviewed procedure: the opt-in `refactor` command requires explicit
   native Rector configuration, an explicit consumer PHP target and reviewed
   rules. Native cache configuration is optional; Rector may use memory in CI.
   Preview is default; only explicit `--apply` grants semantic writes.
   Read `docs/rector.md` for executable-config/autoload limits. Never substitute
   this command for ordinary formatting `fix` or automatically migrate consumers.
6. For requested CI adoption copy `resources/workflows/code-standards.yml` from
   the selected ref after v0 points to a confirmed 0.x release, and use `pull_request` with
   read-only permissions and a hosted runner. No secrets/inherit, write token,
   target event or automatic fix. Required-check settings remain separately owned.
7. Only when magic-number analysis is requested, run `magic-numbers --all` or
   the same immutable `--base`/`--head` selection. It is independent of check/fix
   and does not execute consumer configuration/autoload implicitly. Use
   `--format=xml` for native machine output; preserve failing status and stderr.
   Review native numeric/function/context exceptions explicitly. They are broad
   policy overrides, not per-finding baselines; do not auto-accept existing debt.

This directory is the sole source payload. Installation/host activation is explicit;
copying this skill does not install the runtime or authorize consumer rollout.
LLM Skills owns discovery/install/update; follow the selected ref's distribution
contract rather than adding another copy/symlink installer in this tool.
