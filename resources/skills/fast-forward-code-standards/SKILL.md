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
   invented version. Keep workflow and tool runtime pinned to the same commit.
3. Use `code-standards check --all` for a full tracked scan. For a clean PR head,
   use `check --base=<immutable-base> --head=<immutable-head>`. Check means syntax
   and ECS style only; it does not prove type safety, tests or coverage.
4. Select a local ECS configuration explicitly with `--config=ecs.php` and keep
   imports/overrides. Configuration is executable PHP: never load it with a
   privileged token or on a privileged target event. No incidental dependency update.
5. Only when fixing is requested run `code-standards fix --all`, inspect the diff
   and rerun check. Do not commit/push automatically. Semantic migrations need a
   separate reviewed procedure; none are enabled by this initial package.
6. For requested CI adoption copy `resources/workflows/code-standards.yml` from
   the selected ref, replace both pin placeholders and use `pull_request` with
   read-only permissions and a hosted runner. No secrets/inherit, write token,
   target event or automatic fix. Required-check settings remain separately owned.

This directory is the sole source payload. Installation/host activation is explicit;
copying this skill does not install the runtime or authorize consumer rollout.
