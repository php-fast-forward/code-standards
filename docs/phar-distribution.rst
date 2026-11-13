Compiled distribution
=====================

Consumers install Code Standards through Composer and execute
``vendor/bin/code-standards``. The package points directly at the ready
``dist/code-standards`` PHAR; no consumer build or installer hook runs.
Contributors use the separate ``bin/code-standards`` source entrypoint.

CI build
--------

CI installs the official Box 4.7.1 PHAR through ``setup-php``. Box is not a
Composer dependency of this project or its consumers. Developers do not need to
install it or commit a rebuilt executable manually.

Quality compiles once with PHP 8.3 when a PR changes runtime or build inputs,
including the Composer manifests/lock, source, configuration, resources, stub,
compiled executable or distribution tests. Unknown paths also request a build.
Documentation, ordinary tests, changelog fragments and unrelated workflow-only
changes keep the existing executable. An unavailable comparison base requests
a build. Dependabot updates therefore rebuild when they change the locked
runtime; unchanged compiled bytes do not create an autocommit.
PR input selection compares against the merge base, so unrelated changes on
main do not turn an older documentation branch into a runtime change.

The same built artifact is tested on PHP 8.3, 8.4 and 8.5. There are no three
independent compiler runs. Source checks continue on all three PHP versions.
Artifact consumers use the successful producer's artifact ID; retrying only
failed jobs still downloads that build instead of a nonexistent newer attempt.

Main always qualifies its final merged tree before version preparation. Its
successful Quality call exports the qualified artifact ID. A separate job checks
out that exact source SHA, stages the artifact as data and calls Changelog's
existing Version Action with the same immutable ``base-ref``. The native writer
includes the selected executable, history and fragment removals in one signed
commit and version PR. An advanced main is refused; a different unqualified
base cannot silently replace the validated commit. No compiler or project PHP
runs with the writer token. The artifact is not rebuilt in the writer.
Version writers share native concurrency without cancelling in-flight writes.
A qualification already superseded by another main is skipped before staging;
native preflight still refuses a later advance during the transaction.

Preparation, exact-head feedback and publication share
``generated-files: '["dist/code-standards"]'``. This native policy binds the
artifact digest and executable mode to the owned version transaction. The new
exclusive ``changelog/version-phar`` branch starts this policy without adopting
the older recovery branch under a different settings fingerprint.

``scripts/phar/prepare.php`` has one purpose: prepare a disposable Composer
runtime from the existing lock. Tool libraries live in ``require-dev`` alongside
contributor tools, so their runtime roots must be selected explicitly from
``extra.phar.runtime-packages``. Composer resolves that closed set of locked
package records with plugins and scripts disabled. The checkout manifests,
installed vendor and consumer dependency graph are unchanged.

The workflow then calls Box directly:

.. code-block:: console

   php scripts/phar/prepare.php "$RUNNER_TEMP/standards-runtime"
   box validate "$RUNNER_TEMP/standards-runtime/box.json"
   box compile --no-parallel --sort-compiled-files --config="$RUNNER_TEMP/standards-runtime/box.json"
   box verify "$RUNNER_TEMP/standards-runtime/dist/code-standards"

``box.json`` owns assembly, GZ compression, SHA-256 signature, executable mode
and a fixed archive timestamp. Composer metadata, complete resources and
licenses are retained. The prepared autoloader is preserved. No PHP compactor
or namespace rewriting is enabled, so copied templates and configuration APIs
keep their original contents.

GZ requires ext-zlib. The other platform requirements are declared in
``composer.json``; PHP-only portability is not claimed. The native Composer
platform check remains enabled. A SHA-256 PHAR signature checks integrity; it
is not publisher authentication.

Runtime and consumer configuration
----------------------------------

Some bundled engines use physical entrypoints, realpath and subprocesses.
``scripts/phar/stub.php`` therefore extracts the runtime into a private 0700
system-temporary directory and removes it at normal shutdown. Abrupt termination
can leave it behind. The consumer CWD and configuration paths are preserved.
Default ECS caches stay inside this disposable runtime; explicit native cache
overrides remain authoritative. This is process separation, not a namespace
sandbox.

The default preset does not discover ``ecs.php`` or load the consumer autoloader
implicitly. Select project configuration explicitly:

.. code-block:: console

   vendor/bin/code-standards check --all --config=ecs.php

That PHP configuration can explicitly require its own ``vendor/autoload.php``
and register project or development-package rules through the native ECS API.
Those rules must match the bundled engine APIs. Composer's development install
loads project ``autoload`` and ``autoload-dev`` mappings; ``--no-dev`` intentionally
omits development mappings/packages. See :doc:`api/configuration`.

Validation and automatic refresh
--------------------------------

Source checks and artifact qualification run on PHP 8.3, 8.4 and 8.5. Box validates its configuration and checks
the generated PHAR signature. PHPUnit exercises disposable Composer local,
global and source installs, exact resource preservation, native check/fix,
Rector and magic-number commands, consumer rule loading, CWD and cache cleanup.
These functional tests live in ``tests/Distribution`` and ``tests/Fixtures``;
they are not a second compiler or installer.

Before parsing or executing the committed executable, CI compares every byte to
the fresh build with ``cmp``. A modified stub or forged embedded metadata cannot
authorize execution. Comparison read errors fail; differing bytes require a
refresh. Fixed timestamps and sorted entries make repeat builds reproducible in
the qualified CI environment, not every arbitrary toolchain.

Only after every source check and runtime qualification succeeds does CI retain
an update artifact for a stale PR. Its compiled check fails until the refresh
commit passes its own checks. The trusted ``workflow_run`` callback
copies those bytes with the marketplace autocommit Action; it executes neither
the PHAR nor checkout PHP with its writer token. It checks that the same-repository
PR remains open at the source head before checkout and immediately before the
commit. Closed, deleted or advanced branches are not deliberately recreated or
overwritten; a normal push rejects a concurrent non-fast-forward advance. This
is not a compare-and-swap guarantee against a concurrent branch rewind.

The existing App token triggers checks on the refresh commit. This route selects
unprotected source PR branches targeting main, and excludes main, forks and
native version branches. Only the native version writer changes its owned
transaction. A fork contributor can copy the
qualified artifact into their branch. Installing a development branch gives the
current executable after that branch's refresh and checks finish; an arbitrary
older commit or unvalidated branch cannot contain a later build automatically.
If a merge produces stale committed bytes, main's qualified candidate goes into
the atomic version PR instead of requiring a separate binary repair PR. Until
that PR is merged, the main branch still contains its previous executable.

Changelog publication verifies the owned transaction and exact approved/merged
trees. The version job explicitly invokes native feedback for the returned PR
number and head SHA, independently of automatic event delivery. Merge only
reviewed version PRs whose current-head checks pass. PRs created with
``GITHUB_TOKEN`` can generate
``opened``, ``synchronize`` and ``reopened`` runs requiring approval; do not
assume those runs execute automatically. Native feedback validates ownership
and generated files; it does not replace code review or the project's Quality
checks. Main's explicit Quality dependency qualifies the immutable source base
independently of that approval-dependent delivery.

Publication
-----------

The PHAR is committed before the existing Changelog version-PR/tag lifecycle.
Packagist's standard GitHub archive therefore includes the ready executable.
A release asset alone would not replace that archive. No release or tag is
created by the build/refresh jobs, and a merge does not prove Packagist publication.

``.gitattributes`` preserves the PHAR, Composer manifests, license and entire
``resources/`` tree, including skills and templates. Source and contributor
scripts/tests/configuration are excluded from dist archives. Executable reusable
workflows remain under ``.github/workflows``; copyable callers stay in resources.
``--prefer-source`` also includes development source but still runs the ready
binary. Source is maintained once; bundled library bytes and outer resources
are not deduplicated.
