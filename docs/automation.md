# Automation, refs and bootstrap

Following Changelog's separation:

| Surface | Owner |
| --- | --- |
| `.github/actions/check` | Small CLI adapter; no installation, GitHub API or token handling |
| `.github/workflows/code-standards.yml` | Public reusable consumer entrypoint |
| `.github/workflows/code-standards-check.yml` | Internal reusable check job |
| `.github/workflows/repository-ci.yml` | This project's own tests, style, workflow contracts |
| `.github/workflows/repository-changelog.yml` | Pinned Changelog lifecycle for this project's approved 0.x releases |
| `resources/workflows/code-standards.yml` | Copyable consumer wrapper; never executed here |
| `resources/skills/fast-forward-code-standards` | Canonical portable consumer skill |

The reusable runs only on `pull_request`, on an ephemeral `ubuntu-latest` runner,
with `contents: read`. It rejects privileged target events before checkout. It
uses separate sibling tool and consumer checkouts, exact consumer head SHA,
full Git history and `persist-credentials: false`. No application dependencies,
plugins or scripts are installed. Only the tool's locked dependencies are
installed with plugins/scripts disabled. Config PHP executes without supplied
secrets; consumers must never call this check from a privileged workflow.

The wrapper needs two matching pins: the workflow `uses: ...@<40-character SHA>`
and `runtime-ref: <same SHA>`. GitHub's reusable context identifies the caller,
so the workflow must not derive its tool source ref from `github.workflow_sha`.
Review both refs together. The runtime input is validated before checkout.
An explicit user-selected config remains executable code; only use it in this
unprivileged sandbox. GitHub Actions private-repository sharing policies must
already permit the caller. This project never broadens organization access.

Before a first release, use the reviewed, pushed implementation commit for both
pins. The resource wrapper contains `REPLACE_WITH_REVIEWED_40_CHARACTER_SHA` and
is intentionally inactive until that replacement. It does not reference an
imaginary `v0.1.0`. For a local trial use a path Composer repository or run the
checkout's CLI with `--directory` targeting a disposable consumer. Check archives
and fresh Composer installations before claiming distribution readiness.

A technical empty baseline commit permits an initial functional PR when a remote
repository has no history. All functional content remains on its PR branch.
The initial `.changelog` fragment is explicitly `minor`; Changelog's supported
baseline `0.0.0` yields `0.1.0` without a manual tag or engine change. Before wiring
publication, verify the selected published Changelog tag, source workflow inputs,
release transaction and token availability. The caller selects the verified
published `v0.3.0` commit `85474de5a16bb08f83c9f30e0ed72f331a210f07`.
Its documented no-tag baseline and minor fragment support initial `0.1.0`.
It uses the existing GitHub Actions token; if repository policy prevents PR
creation, report the gate without changing that setting or creating credentials.
0.x policy requires reviewing every prepared version PR; reject any plan
whose next major exceeds zero. There is no claimed generic maximum-major input.
Both Codex and CodeRabbit must actually approve the current HEAD, including the
version PR. An unavailable integration blocks merge and publication. Local review
does not substitute for these remote gates. The privileged Changelog target
event only invokes its pinned trusted lifecycle, never this PHP checking job.

Checking can fail the workflow. Making it a required status is a separate GitHub
protection decision. `fix` has no automatic push workflow and never runs during
PR checks. Full tracked scans occur for Composer/ECS/EditorConfig changes; after
other policy imports change, run an explicit full check in addition to PR checks.
