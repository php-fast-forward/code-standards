# Released v0 channel updater

This repository-only operation follows Changelog's major-channel design. Its
helper and disposable fixtures were adapted from `php-fast-forward/changelog`
commit `e75a9be67114b13e593049ea3a37921259f295f7`, with the Code Standards
namespace/target, an explicit zero-major boundary and PHP 8.3-compatible loops.
The Changelog engine and source repository are not modified.

## Entry point and trust

`.github/workflows/repository-release-channels.yml` runs only after successful
Changelog publication outputs, or explicit manual recovery from main. It checks
the canonical repository and the called job's actual workflow repository/SHA.
Automatic invocation requires the closed, merged `changelog/version` PR from
the same repository into main, with expected SHA equal to its merge commit.
The helper is checked out at that exact trusted workflow revision, never by
resolving main/v0 again or loading a PR-head helper.

Inputs are an existing full stable `v0.MINOR.PATCH` tag and its exact released
commit. The entrypoint rejects another repository. The updater rejects any
nonzero major and never writes tags/releases or merges PRs. No invocation is
scheduled merely because source code or the wrapper exists.

## Effects and safeguards

The updater verifies published non-draft/non-prerelease release state, canonical
publication time, full tag-to-commit binding and a complete bounded catalog. It
peels at most sixteen annotated-tag objects and reads at most ten pages of one
hundred releases. Partial/malformed/incomplete evidence fails closed.

An existing v0 branch must identify an older published release from this major,
and the destination must be a forward descendant. A homonymous v0 tag, foreign
branch, divergent history, ambiguous build-only version tie or stale selector
is refused. Numeric SemVer comparison does not convert components to integers.
Publication/tag evidence is read again immediately before the mutation.

At most one GraphQL `updateRefs` mutation uses the exact `beforeOid` and
`force:false`; an absent branch uses an all-zero lease. A concurrent writer
fails the lease. The resulting ref is read back. Repeating a completed update
returns current without writing; an older selector returns superseded only
when the channel already identifies the newest publication.

## Dependencies and credentials

PHP 8.3+, Git for tests and Composer are required. Symfony HttpClient/Filesystem
are repository development dependencies for this operational helper and its
fixtures. They are not transitively installed for consumer libraries. The
channel job installs the locked development graph with plugins/scripts disabled.
The helper files are excluded from Composer archives and have no production CLI
autoload or runtime coupling.

The job supplies its existing `github.token` through GH_TOKEN with contents
write scoped to that job. It creates no App/token/secret, loads no dotenv, prints
no token and changes no persistent settings. GitHub may require Workflows write
to advance a ref whose historical commit changes workflow files; GITHUB_TOKEN
cannot acquire that by adding a YAML permission. If rejected, report the exact
permission gate and preserve the verified release. Do not extract another token,
force the ref or broaden OAuth/permissions as a workaround.

## Recovery and verification

Success prints JSON state created, advanced, current or superseded, plus channel,
tag and SHA. Exit2 is invalid invocation/missing token; exit1 is verification or
mutation failure. Recognized GraphQL categories are reported without raw server
messages or authentication context. After an uncertain result, read the actual
ref before retrying the same verified selector. Never remove a foreign ref.

`composer test` exercises the actual updater against mock HTTP and disposable
Git CAS transactions; no test contacts GitHub or publishes anything. Workflow
guard tests execute the YAML's real Bash guards with synthetic event contexts.
Actionlint checks the caller and helper workflow. Actual hosted execution and
released-tag/channel readback remain separate evidence.
