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

Quality runs on PHP 8.3, 8.4 and 8.5. Box validates its configuration and checks
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

The PHP 8.3 job uploads its qualified build. A separate job accepts updates only
for same-repository PRs, downloads that run's artifact and invokes
``stefanzweifel/git-auto-commit-action`` for ``dist/code-standards`` only.
It does not execute project PHP or the downloaded PHAR. The existing repository
App supplies contents-write access so its commit triggers normal checks again.
The action pushes without force and does not pull or rebase concurrent changes.
An automatic refresh cannot repeatedly refresh its own commit. Forks retain
read-only checks. Missing update artifacts cause no commit.

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
