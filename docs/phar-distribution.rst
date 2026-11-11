Compiled distribution
=====================

The source is maintained once in this repository. The CI invokes
``scripts/phar/build.php`` to prepare the locked runtime and Box compiles it
using the tracked ``box.json``, writing the executable
``dist/code-standards``. Composer's ``bin`` points directly at that file, following
the concrete ``builds/cpx`` layout in
`CPX 2.1.0 <https://github.com/laravel/cpx/blob/124b3ad19cb98ef0cf6114e56ab9f580c8376ceb/composer.json>`__.
The separate ``bin/code-standards`` remains the contributor source entrypoint.
There is no CPX dependency, installer, updater or consumer build hook.

Build
-----

CI installs the official Box 4.7.1 PHAR through ``setup-php`` and owns the build
and compiled refresh. Contributors do not need Box locally or a manual binary
commit. For an optional local reproduction, put that same Box version on PATH:

.. code-block:: console

   composer install --no-plugins --no-scripts
   composer build
   php dist/code-standards list --raw
   php scripts/phar/verify.php dist/code-standards

The small PHP preparation step uses an exclusive temporary tree, promotes the explicit runtime
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

Box owns file assembly, GZ compression, SHA-256 signing and executable mode.
Its configuration fixes the archive timestamp, keeps complete resources and
Composer metadata, and preserves the prepared autoloader. No PHP compactor or
namespace rewriting is enabled: consumer ECS rules need the existing APIs, and
copyable resources must retain their original bytes. The native Composer
platform check remains; Box's additional requirements checker is disabled for
the custom extraction stub.

Box runs as an isolated build tool, not as a dependency of this package or the
compiled runtime. Installing its source package in the root development graph
replaced PHP 8.0/8.1 polyfills needed by the separately resolved Composer runtime;
using the official PHAR avoids that graph interaction and extra build manifests.
Consumers do not download or execute Box.

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

``dist/code-standards`` is committed in this same repository. Packagist's
standard GitHub zipball therefore contains the ready executable from the tagged
tree. ``composer.json`` points directly at it. Neither a consumer build nor
custom dist metadata nor discovery of release assets is necessary.

The Repository Quality workflow checks out the exact PR head and builds and
verifies a fresh executable on PHP 8.3, 8.4 and 8.5. Its PHP 8.3 job uploads the
qualified build with a receipt binding the source SHA, run ID, run attempt and
SHA-256 digest. A differing committed binary produces an update artifact and
fails CI. Comparison I/O errors and unrelated build errors do not authorize an
update. Developers can commit source changes without rebuilding locally.
Before parsing or executing the committed candidate, CI compares every byte to
the fresh build with native ``cmp``. A difference, including a modified stub or
forged manifest, prevents execution. Self-reported digests are diagnostic
bindings, not a trust anchor. Box's fixed timestamp and sorted entries remove
variable archive timestamps/order. CI requires exact equality on PHP 8.3, 8.4
and 8.5; this does not promise identical bytes across arbitrary toolchains.
The build job has contents-read permissions and no secrets. The separate
``repository-phar-refresh.yml`` callback runs trusted default-branch JavaScript
after Quality completes. It accepts only an open same-repository PR still at the
inspected source head, downloads the exact run/attempt artifact and validates its
bounded payload as data. It never checks out PR code or executes the downloaded
PHAR. Forks and superseded heads do not receive commits.

The callback uses the existing repository App in the ``release-channels``
environment with contents-write permission only. It changes only the already
tracked executable with mode ``100755``. It creates the Git blob, tree and commit
through the Git data API, then uses GitHub's atomic ``updateRefs`` mutation with
``beforeOid`` and ``force: false`` to reject a concurrent head change. A rejected
update can leave unreachable Git objects but cannot replace the branch head.
The App-authored commit triggers the normal checks again; no merge
is enabled. An automation marker prevents a second refresh if that new commit
still fails parity. Identical payloads produce no commit. No token, App grant,
branch protection or release setting is created by this workflow.

This callback becomes available after its workflow and helper reach the default
branch; a feature-branch test cannot prove its live write path. Local API fixtures
exercise provenance, stale heads, payload validation and the atomic mutation.
The first real callback must still qualify the existing App access and GitHub's
binary upload path. Archive download size is bounded before download; exact file
types, sizes and hashes are checked after the official download action extracts
the archive. This does not impose a pre-extraction expansion limit. Until then,
maintainers can obtain the qualified artifact,
restore executable mode and commit it, or use ``composer build`` locally.

This puts the binary in the reviewed commit before Changelog's existing
version-PR/tag lifecycle operates. Changelog remains the only release/tag owner;
no release workflow is introduced here. A future release's
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

The binary is about 50 MiB with the current lock. This intentionally versions
compiled library bytes in Git to support the default Packagist distribution.
Source maintenance remains single, while binary bytes and outer resources are
not deduplicated. A GitHub release asset alone would not automatically replace
the tagged-tree zipball; no alternate transport is configured.

Qualification
-------------

The PHP harness first verifies all compiled source/resource bytes against the
checkout. It creates disposable Git/Composer ZIPs, compares every exported
file hash, preserves the complete resource tree, and installs the actual ready
ZIP through isolated Composer local/global directories. It checks the generated
binary proxy points directly at the compiled file, a single consumer solver
package, no exported source, no-dev reinstall, native formatter/Rector/MND output
and source preservation, and bootstrapping despite a poisoned consumer loader.
The repository CI runs this build and harness on PHP 8.3, 8.4 and 8.5. Those tests
qualify a synthetic distribution; publication remains owned by the existing reviewed Changelog lifecycle.
