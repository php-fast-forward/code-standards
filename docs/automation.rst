Automation, refs and bootstrap
==============================

Following Changelog's separation:

+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| Surface                                               | Owner                                                                                      |
+=======================================================+============================================================================================+
| ``.github/actions/check``                             | Small CLI adapter; no installation, GitHub API or token handling                           |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/code-standards.yml``              | Public reusable consumer entrypoint                                                        |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/code-standards-check.yml``        | Internal reusable check job                                                                |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/repository-ci.yml``               | This project's own tests, style, workflow contracts                                        |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/repository-pr-standards.yml``     | This project's PR-only consumer call, separate from push and manual project tests          |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/repository-changelog.yml``        | Chosen Changelog lifecycle for this project's approved 0.x releases                        |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``.github/workflows/repository-release-channels.yml`` | Published/edited stable release candidate; branch-mode blockers pending |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``resources/workflows/code-standards.yml``            | Copyable consumer wrapper; never executed here                                             |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+
| ``resources/skills/fast-forward-code-standards``      | Canonical portable consumer skill                                                          |
+-------------------------------------------------------+--------------------------------------------------------------------------------------------+

The project's own consumer call is declared in ``repository-pr-standards.yml`` with only the native ``pull_request`` trigger. Its ``reusable-check`` job retains the same inputs and nested job IDs; the top-level workflow is named ``Repository: PR Standards``. ``Repository: Quality`` retains its PHP matrix and project checks on PRs, main pushes and manual dispatch. This separates the PR-only execution surface from events that can write default-branch caches without changing required-check settings.

The reusable runs only on ``pull_request``, on ``ubuntu-latest`` by default,
with ``contents: read``. The optional ``runs-on`` string accepts a JSON array
of Linux self-hosted labels, including both ``self-hosted`` and ``Linux``.
Only same-repository PR jobs that do not execute consumer configuration honor
this custom selection. Forks, non-PR events, other valid JSON shapes/labels,
check jobs with non-empty ``config`` and analysis jobs with Rector enabled use
hosted Ubuntu before scheduling. Invalid JSON or label types fail native workflow
validation; this is not an arbitrary-platform interface. The non-PR guard still
rejects execution. A Bash pre-checkout guard additionally requires actual Linux,
and rejects self-hosted execution with foreign heads, ECS config or Rector.

Same-repository membership is not an approval boundary. The self-hosted path
reads consumer PHP as data with the installation-owned ECS preset, ``php -n -l``
and the PHPMND parser. It does not evaluate consumer ECS config, run Rector or
load consumer autoload files. PHPMND's cwd is its own installed package directory.
Explicit PHP config remains supported on the hosted fallback, without supplied
secrets. This is not a general sandbox: labels do not establish isolation or grant
runner-group access. Use only existing authorized runners with reviewed tool
runtime. No runner is created, reconfigured or registered. Separate sibling tool
and consumer checkouts retain exact head SHA, full Git history and
``persist-credentials: false``. No application dependencies, plugins or scripts
are installed; only locked tool dependencies are installed with plugins/scripts
disabled. Never call this reusable from a privileged workflow.

The selected consumer interface uses ``@v0`` after a published non-prerelease 0.x release has established that GitHub branch. A reviewed full commit remains available for fixed adoption. The reusable exports the native ``job`` context through the supported ``toJSON(job)`` expression in step environment variables. Before checkout, an inline jq guard validates exactly one JSON object, a string repository equal to ``php-fast-forward/code-standards``, and a string SHA containing exactly 40 lowercase hexadecimal characters. Only that validated identity is emitted as step outputs and used for checkout. Invalid, absent or multiple documents emit no checkout outputs and fail closed. The workflow never resolves the moving branch again. ``github.workflow_sha`` identifies the caller and is not used for this purpose; no caller-supplied runtime ref can silently drift from the workflow. The four native workflow identity properties are GitHub.com features; a host without them, including GitHub Enterprise Server, fails this protocol.

This is an explicit runtime JSON boundary. Actionlint still checks the supported context expression and step references; it does not validate fields inside the JSON string. Executable guard regressions own that schema and its failure modes. No dynamic ``fromJSON`` property dereference, ignored linter error or custom validator is used to make an unknown expression property appear supported. The short guard is inline in each bootstrap because no repository helper may run before its source identity has been verified and checked out. jq must be provided by the selected runner; neither workflow downloads an alternate validator. An explicit user-selected config remains executable code; the reusable always routes that job to an unprivileged hosted runner, even for a same-repository PR. GitHub Actions private-repository sharing policies must already permit the caller. This project never broadens organization access.

The formatter subprocess uses an internal packaged worker and ECS's public APIs, not ECS's CLI bootstrap. It keeps the consumer cwd and selected absolute paths without automatically importing consumer ``vendor/autoload.php``. Explicit config is the opt-in boundary for executable consumer bootstrap/custom checkers. The tool's own Composer bootstrap and existing credential filtering remain active; this is not a claim that arbitrary explicit PHP config is sandboxed.

Before a first release, use a reviewed, pushed implementation commit or a local repository reusable call. Own CI exercises the local reusable on PRs and does not depend on ``v0`` already existing. The resource wrapper selects ``@v0`` by the owner's policy; copy it only after that channel has been verified. It does not reference an imaginary ``v0.1.0``. For a local trial use a path Composer repository or run the checkout's CLI with ``--directory`` targeting a disposable consumer. Check archives and fresh Composer installations before claiming distribution readiness.

A technical empty baseline commit permits an initial functional PR when a remote repository has no history. All functional content remains on its PR branch. The contribution deliberately does not create ``CHANGELOG.md``: the normal contribution check forbids changes to consolidated history. The managed version PR creates it while consuming the initial fragment. The initial ``.changelog`` fragment is explicitly ``minor``; Changelog's supported baseline ``0.0.0`` yields ``0.1.0`` without a manual tag or engine change. Before wiring publication, verify the selected published Changelog tag, source workflow inputs, release transaction and token availability. The caller uses Changelog's published ``v0`` branch channel. This GitHub ref is neither a release tag nor a Composer constraint; the called workflow binds its trusted implementation to its actual job repository/SHA, without resolving the moving channel a second time. The qualified lifecycle supports the documented no-tag baseline and minor fragment for initial ``0.1.0``. The caller deliberately omits ``version-pr-title``: the selected lifecycle supplies its native ``chore(release): {repository} v{current_version} → v{next_version}`` template. Repository and version values come from the validated release plan; this is not a manually renamed PR or a title copied into consumer configuration. The generator can refresh an existing managed PR's title without replacing human body text. This change does not itself authorize merging a version PR or publishing. It uses the existing GitHub Actions token; if repository policy prevents PR creation, report the gate without changing that setting or creating credentials. 0.x policy requires reviewing every prepared version PR; reject any plan whose next major exceeds zero. There is no claimed generic maximum-major input. Both Codex and CodeRabbit must actually approve the current HEAD, including the version PR. An unavailable integration blocks merge and publication. Local review does not substitute for these remote gates. The privileged Changelog target event only invokes its selected trusted lifecycle, never this PHP checking job.

External Actions use their own published major channels: ``actions/checkout@v7`` and ``shivammathur/setup-php@v2``. These are moving compatibility channels, not immutable pins, and can receive compatible upstream updates without a local reference change. Review upstream release and runtime requirements before a major-channel upgrade; do not assign one major number to unrelated Actions. Both selected JavaScript Actions use Node 24; checkout requires Actions Runner 2.327.1 or newer. Project-owned jobs retain GitHub-hosted ``ubuntu-latest``. Consumers may explicitly select an existing runner with ``runs-on`` for same-repository data-only PR checks; executable config and Rector retain hosted execution. This input does not enable or update runners or repository access. Local reusable calls remain local, and the consumer workflow retains its separately verified released ``v0`` channel.

Release channels: major branches and latest tag
----------------------------------------------

The Actions channels are branches ``v0`` and future ``v1``, plus the action's
native moving **tag** ``latest``. Concrete ``vMAJOR.MINOR.PATCH`` tags remain
immutable release inputs for Composer/Packagist. ``latest`` is not a numeric
stable Composer version: the installed Composer version parser rejects it as a
version, unlike ``v0`` and ``v0.4.2``. No step exists solely to make latest a branch.

The candidate workflow pins ``Actions-R-Us/actions-tagger`` to immutable commit
``330ddfac760021349fef7ff62b372f2f691c20fb`` with ``v2.0.3`` as its version comment.
The maintainer selected this pin after reviewing the moving-tag security finding.
The published, non-draft, stable v2.0.3 release and its tag were verified on
2026-10-29; the tag resolved to that exact commit. The workflow runs on real
``release: published`` and ``release: edited`` events with
``prefer_branch_releases: true``.
The distributed bundle was tested against a local API
returning actual branch names for branch queries. Alone, it force-downgrades
``v0`` when an older release is edited and can move ``latest`` backwards even
after 1.x. Its latest ref is hardcoded as a tag in branch mode.

A short inline ``github-script`` selection step compensates for that behavior.
It parses concrete stable tags, paginates the tag catalog and compares their
three numeric version components. An incomparable event version fails closed;
this parsing serves comparison rather than a second publication-format validator. The App token and action run only when that release is the newest
stable full tag of its major. ``publish_latest_tag`` is true only when it is also
the newest stable full tag globally. Therefore editing an older 0.x or 1.x release
performs no writes; a later 0.x release after 1.x can advance v0 while leaving
latest unchanged. A 1.x publication updates v1/latest without moving v0. No PHP
updater, checkout, substituted event context or separate latest-ref writer remains.

Tests execute this actual inline selection and the byte-exact distributed bundle
with local API fixtures. They verify older-release no-ops, late 0.x versus global
latest, transition to 1.x and preservation of every concrete version tag. Tags
such as v0/v1/latest are ignored by the stable-version catalog. An unpublished
full version tag can conservatively block promotion; this is a tag catalog, not
a complete published-release catalog. Prerelease lifecycle support is not added.

This is not equivalent to the removed CAS updater. The guard trusts the Changelog-only publication contract and real release event;
it does not re-read live release state or rebind the event SHA to the tag. Exclusive
publication and immutable fulltags are activation requirements, not protections
already installed by this PR. Selection and forced mutation are separate operations. Concurrent tag edits/deletions or another writer can
race that interval; no old-OID lease, ancestry check or resulting-ref readback is
provided. The current branch is not classified as a known release; approved initial
state and native branch protections remain separate requirements. Major/latest
writes are not atomic. The repository concurrency group
does not cancel the running job, but does not exclude other writers or guarantee
a durable FIFO queue. Editing an already-current release may repeat its update.

Publication-only App integration
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Code Standards v0.9.2 was published by ``github-actions[bot]`` through the old
Changelog lifecycle's GITHUB_TOKEN fallback. That token cannot trigger the
separate release workflow. The caller now selects immutable candidate
``41020df0dcd4cb9a2b990f54077553deefbd7608`` combining Changelog PR76 and PR77
in the review-only integration PR78. Its actual
workflow API declares ``publish-app-client-id``, ``publish-environment`` and
``publish-app-private-key``. The existing published v0 revision
``203db95e68fc974e95ba1a921f08d01f88851be1`` does not declare them.

The public Client ID is a non-empty literal, so an absent repository variable
cannot silently select the job-token fallback. Only publication receives that
App selection and ``release-channels`` environment. The caller explicitly
forwards ``secrets.RELEASE_CHANNEL_APP_PRIVATE_KEY`` under the logical
``publish-app-private-key`` workflow-call name. The nested publication job declares
``RELEASE_CHANNEL_APP_PRIVATE_KEY`` so its environment supplies that same-named
secret. Authentication selects a key only with an App Client ID and prefers the
explicit logical key before the environment key. Token mode ignores the staged
environment key; a missing publication key never borrows the legacy App key.
With a non-empty App ID and missing key, upstream authentication fails before
publication. Managed PR authorship remains ``github-actions[bot]``; the publisher
App is not required to be that PR's author. No global App credential input or
``secrets: inherit`` is added, and the release-channel selector remains unchanged.

**This is a blocked draft integration, not an activated release path.** The SHA
is an inspectable PR candidate, not a published Changelog version. The compatible
adoption order is:

#. Review and qualify both upstream changes and the integrated candidate.
   Let the owner merge/publish a compatible release. Verify that released revision actually
   contains the three inputs and nested publication/authentication routing.
#. Replace this candidate pin with that verified published revision before stable
   adoption, or obtain explicit acceptance of using the reviewed unreleased SHA.
   Never send the new inputs to the old v0 and assume compatibility.
#. Verify the separately authorized configuration owner's staged environment,
   valid secret name and protection rules through metadata; key validity and
   actual publication remain separate evidence. This caller patch does not
   create or configure those resources.
#. Only after those gates, let the owner merge the caller and the next approved
   version PR. Observe that real publication/channel run separately; no rerun,
   edited release, manual release or ref mutation is used to manufacture evidence.

Missing deployment configuration is not repaired by falling back to GITHUB_TOKEN.
This patch neither republishes v0.9.2 nor backfills its channel event.

The common channel template selects ``release-channels`` and mints a revocable
App token scoped to this repository with Contents/Workflows write. The ambient
token has Contents read. Read-only organization metadata on 2026-10-28 identifies
App 5513989 (``fast-forward-release-channels``), installation 176263242, with all
organization repositories selected and Contents/Workflows write, PR read and
Metadata read. This includes Code Standards; no installation or permission
expansion is indicated by those metadata. The configuration owner subsequently staged the authorized environment separately.
Read-only metadata on 2026-10-29 confirms ``release-channels`` and the valid
``RELEASE_CHANNEL_APP_PRIVATE_KEY`` secret name. This code task did not read the
value, mint a token or modify settings; name metadata does not establish key
validity or end-to-end event delivery. GitHub cannot recover an existing secret
value for migration.

The activation plan preserves branches, without branch-to-tag migration:

* Preserve the existing verified v0 branch; future v1 is a branch. Reserve
  homonymous tags v0/v1 against shadowing; do not remove foreign refs.
* Approve major-branch creation/update only for the chosen App. Keep native
  deletion and non-fast-forward protection without bypass. The action's
  ``force:true`` request does not itself prove whether a forward update passes
  those rules; qualify server acceptance only after explicit authorization.
* Approve tags/latest creation/update only for that App and deletion blocking
  without bypass. Do not reserve latest against the writer or treat it as a
  concrete immutable release tag. No heads/latest channel is created.
* Full version tags: creation only by the publisher App; update/deletion blocked
  without bypass. No concrete release tags are rewritten for channels.
* The proposed environment permits main for publication and full tags v*.*.*
  for release events. Workflow code at those tags can access its key; constrain
  full-tag creation before claiming a dedicated authority boundary.
* Verify the published credential contract and approve the exact App/environment
  changes before activation. No security, secret, environment, channel ref or
  release is changed as a test.

GitHub v0 can span breaking development minors and is independent of Composer
constraints and CPX resolution. No channel is precreated at main.


Checking can fail the workflow. Making it a required status is a separate GitHub protection decision. ``fix`` has no automatic push workflow and never runs during PR checks. Full tracked scans occur for Composer/ECS/EditorConfig/root or nested ``.gitignore`` changes, including removal and rename. Policy-name invalidation is case-insensitive: a case-folding filesystem can let Git read ``.GiTiGnOrE`` as ``.gitignore``. On a case-sensitive filesystem, this conservative detection can cause an extra full scan; it does not change which ignore rules Git applies. After other policy imports change, run an explicit full check in addition to PR checks.

Trusted private consumer example (existing repository variable, no secret):

.. code-block:: yaml

   jobs:
     style:
       uses: php-fast-forward/code-standards/.github/workflows/code-standards.yml@v0
       with:
         runs-on: ${{ vars.CI_LINUX_RUNNER || '["ubuntu-latest"]' }}

Use this input only after the selected published channel includes it. For example, ``CI_LINUX_RUNNER`` can contain ``["self-hosted","Linux","X64","local-ci"]``. Existing callers retain hosted defaults. A non-empty ECS config or enabled Rector forces its job back to hosted Ubuntu. Confirm the runner is already authorized for the private consumer and provides Bash, jq, Git and PHP >=8.3, with the Node 24-compatible runner required by these Actions. Optional Magic Numbers also requires DOM, POSIX process-group functions and executable /usr/bin/setsid. Foreign PRs never use the custom selection; billing or availability of the hosted fallback remains the consumer account responsibility.

Composer metadata gate
----------------------

``normalize-composer`` is an optional boolean reusable input, defaulting to
``false``. With ``true``, the internal check job also runs the reviewed tool's
read-only metadata command against the exact consumer root, regardless of the
changed PHP selection. No consumer packages are installed for this gate. The
repository quality workflow and PR caller enable it for this project's own
manifest; they do not impose it on other callers. Nonzero status fails normally,
without autofix, lock maintenance or new permissions. See
`Composer normalization <composer-normalization.rst>`__ for paths, exit codes and
consumer adoption.

Runner contract references
--------------------------

GitHub documents `runner environments and allowed contexts
<https://docs.github.com/en/actions/reference/workflows-and-actions/contexts>`__
and `runner access from reusable workflows
<https://docs.github.com/en/actions/reference/workflows-and-actions/reusing-workflow-configurations#how-reusable-workflows-use-runners>`__.
The fork fallback is this reusable's stricter policy, not a claim that custom
runner labels themselves establish trust or isolation.

Environment secret identifier correction
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

GitHub rejected ``app-private-key`` as a repository/environment secret name with
HTTP 422: those identifiers accept alphanumeric characters and underscores.
Hyphens remain valid in logical reusable-workflow inputs; these are separate
contracts. The compatible candidate adds the valid nested environment-secret
declaration ``RELEASE_CHANNEL_APP_PRIVATE_KEY`` while preserving the public
``publish-app-private-key`` logical input. The channel and caller now read the
valid environment identifier. This code change does not create or read any key.

This defect is independent of the version-PR preparation failure on 2026-10-29.
The latter was reproduced before writes: provenance resolution tried to read
the original squash-merged PR94 head, absent from the main-only Git checkout.
Fetching that exact object into an isolated fixture changed the same planner
from refused to dry-run. Its provider correction and managed PR98 recovery are
separate; changing a secret identifier does not repair or retry that transaction.

Version preparation recovery
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

The original ``changelog/version`` branch of PR98 ends at the human merge
``48119d8d4bf3b77d488eba12b1f0515453b3f371``. Its content is the exact automatic
merge of the signed Bot head and the then-current main: the remaining managed
patch is byte-identical to the Bot patch, and the human delta is byte-identical
to the integrated main delta. This preserves the human work but does not grant
the human commit signed Bot ownership. No trailers are forged or waived.

The caller reserves ``changelog/version-recovery-98`` for fresh automation. After
this reviewed caller and a compatible corrected runtime are integrated, the next
trusted main event plans from the fresh main and creates a new signed Bot commit
and version PR on that exclusive branch. The old branch, commit, PR body and
review history remain available. Do not reset, force-update or delete PR98's
branch. If either the fresh main or the reserved recovery branch changes during
preparation, fail closed and re-inspect before proceeding. Close the superseded
PR only after separate authorization and verification of the replacement.

A version PR on the recovery branch still requires successful current-head
checks, ownership proof and review before an approved merge/publication. This
configuration change prepares recovery; it does not create that PR or publish a
release. A missing original squash PR object is repaired through exact verified
object acquisition. The HTTP boundary authenticates the pack request; local Git
receives only credential-free pack bytes and never a token, credential helper
or network-fetch command. Never accept an unproved association or fetch every
PR ref.
