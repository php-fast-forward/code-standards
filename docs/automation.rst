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
| ``.github/workflows/repository-release-channels.yml`` | Internal released-only v0 update after successful publication; explicit recovery from main |
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

The selected consumer interface uses ``@v0`` after a published non-prerelease 0.x release has established that GitHub branch. A reviewed full commit remains available for fixed adoption. The reusable exports the native ``job`` context through the supported ``toJSON(job)`` expression in step environment variables. Before checkout, an inline jq guard validates exactly one JSON object, a string repository equal to ``php-fast-forward/code-standards``, and a string SHA containing exactly 40 lowercase hexadecimal characters. Only that validated identity is emitted as step outputs and used for checkout. Invalid, absent or multiple documents emit no checkout outputs and fail closed. The workflow never resolves the moving branch again. ``github.workflow_sha`` identifies the caller and is not used for this purpose; no caller-supplied runtime ref can silently drift from the workflow. The same protocol protects the channel publisher's trusted implementation. The four native workflow identity properties are GitHub.com features; a host without them, including GitHub Enterprise Server, fails this protocol.

This is an explicit runtime JSON boundary. Actionlint still checks the supported context expression and step references; it does not validate fields inside the JSON string. Executable guard regressions own that schema and its failure modes. No dynamic ``fromJSON`` property dereference, ignored linter error or custom validator is used to make an unknown expression property appear supported. The short guard is inline in each bootstrap because no repository helper may run before its source identity has been verified and checked out. jq must be provided by the selected runner; neither workflow downloads an alternate validator. An explicit user-selected config remains executable code; the reusable always routes that job to an unprivileged hosted runner, even for a same-repository PR. GitHub Actions private-repository sharing policies must already permit the caller. This project never broadens organization access.

The formatter subprocess uses an internal packaged worker and ECS's public APIs, not ECS's CLI bootstrap. It keeps the consumer cwd and selected absolute paths without automatically importing consumer ``vendor/autoload.php``. Explicit config is the opt-in boundary for executable consumer bootstrap/custom checkers. The tool's own Composer bootstrap and existing credential filtering remain active; this is not a claim that arbitrary explicit PHP config is sandboxed.

Before a first release, use a reviewed, pushed implementation commit or a local repository reusable call. Own CI exercises the local reusable on PRs and does not depend on ``v0`` already existing. The resource wrapper selects ``@v0`` by the owner's policy; copy it only after that channel has been verified. It does not reference an imaginary ``v0.1.0``. For a local trial use a path Composer repository or run the checkout's CLI with ``--directory`` targeting a disposable consumer. Check archives and fresh Composer installations before claiming distribution readiness.

A technical empty baseline commit permits an initial functional PR when a remote repository has no history. All functional content remains on its PR branch. The contribution deliberately does not create ``CHANGELOG.md``: the normal contribution check forbids changes to consolidated history. The managed version PR creates it while consuming the initial fragment. The initial ``.changelog`` fragment is explicitly ``minor``; Changelog's supported baseline ``0.0.0`` yields ``0.1.0`` without a manual tag or engine change. Before wiring publication, verify the selected published Changelog tag, source workflow inputs, release transaction and token availability. The caller uses Changelog's published ``v0`` branch channel. This GitHub ref is neither a release tag nor a Composer constraint; the called workflow binds its trusted implementation to its actual job repository/SHA, without resolving the moving channel a second time. The qualified lifecycle supports the documented no-tag baseline and minor fragment for initial ``0.1.0``. The caller deliberately omits ``version-pr-title``: the selected lifecycle supplies its native ``chore(release): {repository} v{current_version} → v{next_version}`` template. Repository and version values come from the validated release plan; this is not a manually renamed PR or a title copied into consumer configuration. The generator can refresh an existing managed PR's title without replacing human body text. This change does not itself authorize merging a version PR or publishing. It uses the existing GitHub Actions token; if repository policy prevents PR creation, report the gate without changing that setting or creating credentials. 0.x policy requires reviewing every prepared version PR; reject any plan whose next major exceeds zero. There is no claimed generic maximum-major input. Both Codex and CodeRabbit must actually approve the current HEAD, including the version PR. An unavailable integration blocks merge and publication. Local review does not substitute for these remote gates. The privileged Changelog target event only invokes its selected trusted lifecycle, never this PHP checking job.

External Actions use their own published major channels: ``actions/checkout@v7`` and ``shivammathur/setup-php@v2``. These are moving compatibility channels, not immutable pins, and can receive compatible upstream updates without a local reference change. Review upstream release and runtime requirements before a major-channel upgrade; do not assign one major number to unrelated Actions. Both selected JavaScript Actions use Node 24; checkout requires Actions Runner 2.327.1 or newer. Project-owned jobs retain GitHub-hosted ``ubuntu-latest``. Consumers may explicitly select an existing runner with ``runs-on`` for same-repository data-only PR checks; executable config and Rector retain hosted execution. This input does not enable or update runners or repository access. Local reusable calls remain local, and the consumer workflow retains its separately verified released ``v0`` channel.

Successful publication outputs start the internal channel operation directly; the workflow does not depend on another event being generated by GITHUB_TOKEN. It verifies a published 0.x tag and its exact commit before one conditional branch update. Read `the repository helper contract <https://github.com/php-fast-forward/code-standards/blob/main/.github/update-release-channels.md>`__. No channel is precreated at main, and the helper cannot create releases or tags. GitHub ``v0`` may advance across breaking development minors. It is not a Composer ``^0.1`` constraint or a CPX package version; select those versions independently.

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
