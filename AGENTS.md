# Fast Forward Code Standards

## Purpose

Own focused PHP style rules, syntax validation, CLI and changed-file automation.
Application tests, coverage, reports, dependency updates and documentation
rendering belong to other domains. This first increment does not claim PHPStan
type-analysis or Rector migration parity with DevTools.

## Ownership

`src/` owns reusable behavior; the CLI and Actions orchestrate the same services.
The sole distributable skill is under `resources/skills/fast-forward-code-standards`.
Consumer examples live under `resources`, executable Actions under `.github/actions`,
reusables under `.github/workflows/code-standards*.yml`, own event workflows use
`repository-`. Ordinary folders share this root contract.

## Local Contracts

- English for code and documentation. PHP runtime ^8.3; no DevTools dependency.
- `check` MUST NOT pass `--fix`, update dependencies, execute application tests,
  publish or change source. Tool caches may live in the system temporary directory.
- `fix` is explicit, local and potentially partial on failure; inspect its diff.
- No implicit risky migration, PHP-version migration or semantic refactor profile.
  Future semantic work requires an explicit consumer target PHP and opt-in.
- PR checks use `pull_request`, exact head/base, merge-base comparison and Git's
  NUL-delimited protocol. Never run untrusted PHP on privileged target events or
  self-hosted runners. No secrets are accepted by the checking reusable.
- Never copy credentials, change branch protections or make a status required.
- Use `codex/` branches, independent review and exact-SHA evidence before push.
  Create/deduplicate the initial issues first. Each PR including a version PR
  requires real successful checks AND Codex AND CodeRabbit reviews on its
  current HEAD with no pending findings before merge. Unavailable, skipped,
  requested-only or stale reviews do not count. Do not expand permissions to
  install those integrations. Technical review replies are authorized here.
- During 0.x, compatible corrections use patch and functionality/breaking
  development changes explicitly use minor. No major release is authorized.
  Changelog automation owns version PRs/tags; never create manual bootstrap tags.

## Work Guidance

Run commands through RTK on this host. Preserve unrelated changes. Add regression
fixtures and unique `.changelog/*.md` fragments; never consolidate history in
ordinary work. Select a verified Changelog release before wiring its lifecycle.
Repository creation, authentication and distribution are separate from local tests.

## Verification

`composer install --no-plugins --no-scripts`, `composer test`,
`composer validate --strict`, `php bin/code-standards check --all`,
`actionlint .github/workflows/*.yml`, and `git diff --check`.
Tests use disposable repositories and synthetic files, never consumers' worktrees.
No self-hosted CI dispatch. Record actual commands/results and independent review
for the exact local base/head before publication; do not claim remote CI evidence.

## Child DOX Index

- [README.md](README.md): library/CLI adoption, explicit fix and tool scope.
- [Policy comparison](docs/policy.md): evidence, rule differences and extraction gates.
- [Automation](docs/automation.md): trust, ref/bootstrap contracts and consumer adoption.
- [Instruction map](docs/instruction-map.md): single root scope and skill ownership.
- [Consumer skill](resources/skills/fast-forward-code-standards/SKILL.md): portable consumption procedure; no automatic installation or rollout.
