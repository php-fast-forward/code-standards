Compiled distribution
=====================

The source is maintained once in this repository. ``composer build`` invokes the
PHP builder at ``scripts/phar/build.php`` and writes the executable
``builds/code-standards``. Composer's ``bin`` points directly at that file, following
the concrete ``builds/cpx`` layout in
`CPX 2.1.0 <https://github.com/laravel/cpx/blob/124b3ad19cb98ef0cf6114e56ab9f580c8376ceb/composer.json>`__.
The separate ``bin/code-standards`` remains the contributor source entrypoint.
There is no CPX dependency, installer, updater or consumer build hook.

Build
-----

.. code-block:: console

   composer install --no-plugins --no-scripts
   composer build
   php builds/code-standards list --raw
   php scripts/phar/verify.php builds/code-standards

The builder uses an exclusive temporary tree, promotes the explicit runtime
roots listed in ``extra.phar.runtime-packages`` to their exact locked versions,
and asks Composer to resolve that graph using only locked package metadata.
It invokes the Composer executable from the installed locked ``composer/composer``
package, so an unrelated global Composer update cannot change generated lock metadata.
Contributor/test packages are excluded by resolution, rather than stripping
files from dependencies. Generated proxies, resources and licenses are retained.
The source manifests, lock and installed vendor are never rewritten by the build.
The payload's root version comes from the tracked Composer manifest, defaulting
to ``dev-main`` when absent; ambient Composer root-version guesses do not change
its executable contents.
This is the same necessary dependency promotion as
`CPX's builder <https://github.com/laravel/cpx/blob/124b3ad19cb98ef0cf6114e56ab9f580c8376ceb/scripts/build.sh>`__;
simply moving requires to require-dev would omit the runtime from a no-dev build.

The GZ PHAR has a SHA-256 integrity signature, an embedded version/reference
inventory and executable mode. Compression requires ext-zlib. Phar and all
extensions required by the locked runtime are declared in Composer metadata;
PHP-only is not an accurate portability claim. DOM remains optional for XML.
The earlier uncompressed proof was 142,765,641 bytes; GZ was 48,600,589 bytes.
The final size varies with source/locked packages. Libraries occur inside the
artifact, and outer copyable resources also occur inside it; source maintenance
is single, bytes are not deduplicated.

Filesystem runtime
------------------

The current engines use realpath, workers, filesystem entrypoints and Composer's
schema. PHP's phar stream cannot satisfy these unchanged filesystem contracts.
The executable therefore extracts into a random private 0700 temporary directory
and deletes it at normal shutdown. There is no shared cache. Abrupt termination
can leave that directory behind. This is process separation, not a namespace
sandbox: executable consumer configurations may load consumer classes explicitly.
CWD, project/config paths and the check command's source-preservation contract
continue to apply.

The default ECS cache lives inside that extracted runtime and is removed with it.
Native cache metadata contains installation paths, so independently extracted
invocations deliberately do not reuse it. Repeated compiled checks/fixes do not
accumulate persistent per-invocation policy directories in system temporary storage.
Source installations retain their stable checkout/policy cache identity; explicit
native ``withCache()`` overrides remain authoritative and outside this cleanup.

Consumer ECS configuration
--------------------------

The tool's default preset does not require the consumer's Composer loader or
discover ``ecs.php`` automatically. Select the project configuration explicitly
with ``vendor/bin/code-standards check --all --config=ecs.php``. To use project
classes or development rule packages, that configuration opts into its own loader:

.. code-block:: php

    <?php

    require __DIR__ . '/vendor/autoload.php';

    return FastForward\CodeStandards\Config\ECSConfig::configure()->withRules([
        Project\CodingStandard\ProjectFixer::class,
        Vendor\CodingStandard\PackageFixer::class,
    ]);

Install those rule packages in the project's ``require-dev``. Composer generates
project ``autoload`` and ``autoload-dev`` mappings during development installation;
``composer install --no-dev`` intentionally omits development packages/mappings.
Rules must implement APIs compatible with the bundled ECS/PHP-CS-Fixer/PHPCS
versions. Loading the project autoloader is explicit execution inside the worker,
not namespace isolation or a guarantee that conflicting engine versions coexist.

The distribution harness installs a local synthetic rule package through real
Composer ``require-dev`` and registers separate project ``autoload`` and
``autoload-dev`` fixers. It verifies all three rules report their own marker,
``check`` preserves source, ``fix`` changes every marker, and rechecking succeeds
without changing Composer metadata or configuration. Existing source integration
tests also exercise explicit loader opt-in; their synthetic loader alone was not
proof of Composer-generated development autoloading in the PHAR.

Standard Composer distribution
-------------------------------

``builds/code-standards`` is committed in this same repository. Packagist's
standard GitHub zipball therefore contains the ready executable from the tagged
tree. ``composer.json`` points directly at it. Neither a consumer build nor
custom dist metadata nor discovery of release assets is necessary.

The Repository Quality workflow builds and verifies a fresh executable on PHP
8.3, 8.4 and 8.5. Its PHP 8.3 job uploads that build as an Actions artifact named
after the exact commit SHA before checking the committed artifact. A stale
committed binary therefore fails CI while the fresh reviewed build remains
available to update that PR.
Before executing the committed candidate, CI opens it only as archive data and
compares the entire stub and sorted path/content hashes with that fresh build.
Extra/missing/changed entries, links and serialized metadata are rejected.
Self-reported manifest digests are diagnostic bindings, not a trust anchor for
the executable. Container timestamps/compression are not executable content.
It uses contents-read permissions, does not write Git or tags, and runs no
release. Maintainers can obtain the artifact from that reviewed run, restore
executable mode with ``chmod +x builds/code-standards``, and include it in the
same PR as source/manifest/lock/build-recipe changes. Alternatively ``composer build``
replaces the tracked executable locally. CI rejects a missing or stale binary
by comparing actual runtime identity, source/resource hashes, the original lock
digest, Composer build manifest and PHP build recipe with the reviewed checkout.

This puts the binary in the reviewed commit before Changelog's existing
version-PR/tag lifecycle operates. Changelog remains the only release/tag owner;
no release workflow or token permissions are changed here. A future release's
tag retains the artifact already in its commit. A merge still does not itself
prove a release has been published or indexed by Packagist.

``.gitattributes`` exports the compiled file, Composer manifests, license and
entire ``resources/`` tree, including skills and future templates. Source,
config, tests and contributor scripts are excluded from archives. Copyable
workflow callers remain under ``resources/workflows``; executable reusable
workflows remain under ``.github/workflows`` for existing ``uses`` calls.
``--prefer-source`` includes source files alongside the ready committed binary;
it ignores export-ignore but still needs no consumer build. Contributors use
the separate source entrypoint and dev dependencies when changing the tool.

The binary is about 46.4 MiB with the current lock. This intentionally versions
compiled library bytes in Git to support the default Packagist distribution.
Source maintenance remains single, while binary bytes and outer resources are
not deduplicated. A GitHub release asset alone would not automatically replace
the tagged-tree zipball; no alternate transport is configured.

Qualification
-------------

The PHP harness creates disposable Git/Composer ZIPs, compares every exported
file hash, preserves the complete resource tree, and installs the actual ready
ZIP through isolated Composer local/global directories. It checks the generated
binary proxy points directly at the compiled file, a single consumer solver
package, no exported source, no-dev reinstall, native formatter/Rector/MND output
and source preservation, and bootstrapping despite a poisoned consumer loader.
The repository CI runs this build and harness on PHP 8.3, 8.4 and 8.5. Those tests
qualify a synthetic distribution; publication remains owned by the existing reviewed Changelog lifecycle.
