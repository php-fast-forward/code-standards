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
Contributor/test packages are excluded by resolution, rather than stripping
files from dependencies. Generated proxies, resources and licenses are retained.
The source manifests, lock and installed vendor are never rewritten by the build.
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

Distribution and publication gate
---------------------------------

No binary is committed or release activated by this draft. The default GitHub
zipball used by Packagist contains only the tagged Git tree. ``export-ignore``
removes files; it cannot add a generated artifact. A GitHub release asset alone
will not be discovered automatically as the Composer dist.

The minimal standard Packagist route is to put the ready ``builds/code-standards``
artifact in the release's tagged tree. GZ keeps the measured artifact below
GitHub's 100 MiB per-file limit. This requires a deliberate decision to version
binary bytes in this same repository before publication. Source-only tags are
not installable with the new binary contract and must not be released.

An alternative ready ZIP in this repository's release assets requires explicit
dist metadata and verified Packagist ingestion before adoption. Composer's
`dist schema <https://getcomposer.org/doc/04-schema.md#dist>`__ supports a URL,
including version/reference placeholders, and local VCS tests preserve it.
That fixture is not proof of live Packagist ingestion. No alternate metadata,
publication workflow, credentials or release asset transport is activated here.

``.gitattributes`` exports the compiled file, Composer manifests, license and
entire ``resources/`` tree, including skills and future templates. Source,
config, tests and contributor scripts are excluded from archives. Copyable
workflow callers remain under ``resources/workflows``; executable reusable
workflows must remain under ``.github/workflows`` for existing ``uses`` calls.
With a committed binary, ``--prefer-source`` includes source as well; it does
not honor export-ignore. Without a committed binary, source installs require a
contributor build and do not satisfy the no-build consumer contract.

Qualification
-------------

The PHP harness creates disposable Git/Composer ZIPs, compares every exported
file hash, preserves the complete resource tree, and installs the actual ready
ZIP through isolated Composer local/global directories. It checks the generated
binary proxy points directly at the compiled file, a single consumer solver
package, no exported source, no-dev reinstall, native formatter/Rector/MND output
and source preservation, and bootstrapping despite a poisoned consumer loader.
The repository CI runs this build and harness on PHP 8.3, 8.4 and 8.5. Those tests
qualify a synthetic distribution; real publication remains the separate gate above.
