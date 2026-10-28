PHAR distribution investigation
===============================

Status: experimental; no production manifest, binary endpoint, release workflow
or publishing strategy is changed. The installed library API remains available.
This is a partial follow-up to issue #2, not a claim that all its acceptance
criteria pass. PRs #70 and #82 already cover portable skills; #92 already fixes
CPX autoload. They are prerequisites, not work repeated here.

Transport decision
------------------

A standard Packagist GitHub package advertises a Git reference and its source
archive. ``export-ignore`` filters paths already in that tree. It cannot inject
a PHAR which exists only as a GitHub Release attachment. Composer also ignores
a dependency's scripts and development requirements during consumer installation.

A single repository can maintain source once and ship a ready artifact through
Composer local and global installs in two ways. One is committing the generated
artifact before publishing its reference; this adds binary bytes to Git history
and needs an explicit publication decision. The other is declaring an explicit,
version-specific ``dist`` object in the reference's ``composer.json`` and pointing
it at a ready release ZIP. Composer 2.10.3's ``VcsRepository::preProcess()`` preserves
an existing ``dist`` instead of replacing it with the GitHub driver's default
zipball. No separate Composer metadata repository is inherently required.

The official Packagist updater imports VCS packages and persists their dist
fields for new versions. This supports the custom-dist direction, but does not
prove this package's live Packagist metadata before publication. Do not rewrite
existing stable releases or use a shared latest-asset URL. The version PR needs
to bind the ZIP URL to that version, and release orchestration must establish
artifact availability and matching contents before consumers resolve it.
No such production integration is activated here; coordinate it with the release
owner rather than changing the release-channels workflow.

A release attachment alone is insufficient: the default driver does not discover
assets automatically. ``export-ignore`` cannot inject absent files. With an
explicit release dist and no PHAR in Git, ``--prefer-source`` gets source without
the ready artifact and cannot satisfy the no-build execution contract. That is
an explicit supported-transport boundary, not an automatic fallback installer.

Generated payloads necessarily contain library bytes. Source is maintained once;
this does not promise deduplication against the application's vendor libraries.
The isolation goal is removing bundled dependencies from the consumer's Composer
solver graph. Executable PHP configuration may deliberately import application
classes; no PHAR or separate graph is a PHP namespace sandbox.

CPX and Box evidence
--------------------

CPX 2.1.0, source ``124b3ad19cb98ef0cf6114e56ab9f580c8376ceb``, declares PHP
``^8.3`` in ``require`` and its libraries in ``require-dev``. Its
`build script <https://github.com/laravel/cpx/blob/124b3ad19cb98ef0cf6114e56ab9f580c8376ceb/scripts/build.sh>`__
promotes a finite runtime package list to exact locked requirements in a temporary
manifest, installs that closure, runs Box 4.7.0, then restores the contributor
manifest and dependencies. Its ``box.json`` includes source, files and vendor,
retains Composer files and compresses with GZ. Moving dependency fields alone is
insufficient: `Box excludes dev files by default <https://box-project.github.io/box/configuration/>`__.

For this tool, keep build-only tooling in development requirements and derive a
staged runtime from explicitly selected runtime roots and their locked closure.
Do that in a disposable directory rather than replacing the working vendor tree.
Do not resolve an unconstrained newer closure during release. The stage must not
require the artifact currently being built. Own source validation can bootstrap
from the contributor installation; eventual self-consumption selects an already
published compatible tool. This breaks a solver cycle only for edges actually
removed from runtime; it does not solve arbitrary build cycles.

Runtime obstacles and minimum experiment
----------------------------------------

The existing provider calls ``realpath()`` on the ECS installation and passes
absolute filesystem paths to workers, Rector and PHPMND. PHP explicitly documents
that `realpath cannot resolve paths inside PHARs <https://www.php.net/manual/en/function.realpath.php>`__.
The native PHPMND entrypoint imports its CWD vendor autoloader, but the existing
Code Standards adapter already runs it from its own installation directory.
Rector has separate project-loading routes and an explicit configuration contract.
Materialization must preserve these actual adapter boundaries; it does not itself
establish namespace isolation.
The installed ECS and Rector entrypoints are scoped PHP source trees; PHPStan
already ships a PHAR, but that does not supply ECS's factory, custom Code Standards
rules, Rector configuration or Composer's schema resource. No inspected upstream
API establishes that all these existing filesystem contracts work unchanged from
``phar://``. A direct, streaming PHAR needs further service/worker investigation
before it can replace the existing runtime.

``scripts/phar/build.php`` is a native-PHAR experimental packer, not a proposed
replacement for Box's production build lifecycle. Given an installed no-dev tree,
it packages source, config, entrypoints, vendor, the complete resources tree,
Composer manifests and licenses without dependency resolution. Its uncompressed
payload avoids introducing a GZ requirement merely for the experiment. The
optional ``--gzip`` mode tests per-file GZ compression and adds ``ext-zlib`` to the
candidate manifest; it is a measured alternative rather than a hidden assumption. A SHA-256
PHAR checksum detects corruption; it is not publisher authentication.

The experimental stub materializes that tree into a fresh private temporary
directory per invocation, selects its own autoloader, preserves CWD and arguments,
and removes the tree at normal PHP shutdown. There is no persistent cache,
installer, updater, lock service or automatic Composer execution. This tests the
smallest filesystem-compatible transport before any custom runtime framework.
Repeated extraction has disk/time cost; abrupt termination can leave a directory.
Cleanup, concurrent calls, permissions, TMPDIR paths with spaces, corruption and
real native engine/config behavior all require execution evidence before adoption.

The current lock's declared runtime extension floor includes ``openssl``,
``iconv``, ``tokenizer``, ``filter``, ``hash``, ``json``, ``pcre`` and ``reflection``.
PHAR execution also needs ``ext-phar``; scoped nested tools may impose additional
requirements not visible in the outer Composer graph. Some modules are standard
PHP modules, but that does not establish that every PHP 8.3 build provides them.
Optional XML reports have a separate DOM contract. Do not advertise a PHP-only
manifest or erase extension checks until a minimal-extension PHP 8.3 run proves it.
The staged transport fixture keeps declared extensions in its candidate manifest.

Reproducible experiment and remaining gates
------------------------------------------

On a host below the agreed one-minute load threshold of 14, prepare a disposable
runtime in the system temporary directory, outside synchronized project folders,
by copying the reviewed source ``src``, ``config``, ``bin``, ``resources``,
``composer.json``, ``composer.lock`` and ``LICENSE``; run ``composer install
--no-dev --no-plugins --no-scripts`` there using an isolated Composer home/cache. Set ``COMPOSER_ROOT_VERSION`` to
``dev-phar-poc`` to label the synthetic source snapshot explicitly; the harness
records the actual installed root identity rather than inferring a release.
Do not reuse a vendor tree installed with dev dependencies. Then run::

    python3 scripts/phar/verify.py /absolute/no-dev-runtime /absolute/new-experiment --gzip
    python3 scripts/phar/verify-runtime.py /absolute/passed-experiment /absolute/new-native-fixture php
    python3 scripts/phar/verify-vcs-dist.py /absolute/passed-experiment /absolute/new-vcs-fixture

The harness refuses an existing experiment directory. It writes command receipts
and logs, builds a synthetic package and commits that fixture locally solely to
exercise Git archives. It creates no product tag or release and changes no real
consumer. It compares Git and Composer ZIP contents byte for byte, checks every
resource, installs with actual Composer ``require --prefer-dist``, tests an
isolated ``composer global require`` and ``--prefer-source``, verifies the solver
contains only the synthetic tool package, runs help and Git selection, and checks
a deliberate style violation through an executable fixture config without changing
fixture source or manifests. A successful receipt is transport evidence only.

Observed local evidence (2026-10-28)
------------------------------------

The locked no-dev stage contains 52 packages, compared with 80 in the contributor
installation. Versions match the existing locked installation; 28 dev-only
packages are excluded. No files within the selected runtime packages were pruned:
resources, schemas, licenses and generated entrypoints remain intact. This is the
smallest tested closure of the current runtime requirements, not a proof that no
future dependency can be removed.

The uncompressed PHAR is 142,765,641 bytes (about 136 MiB). It runs on the tested
PHP 8.5 configuration but fails before its stub on PHP 8.4 with the default 128 MiB
memory limit. GZ produces a 48,600,589-byte PHAR (about 46 MiB), below 100 MiB,
with ``ext-zlib`` declared. Its Git and Composer ZIPs have identical file/hash
maps; their ZIP sizes differ because the archivers encode the containers
differently. The uncompressed filesystem payload is 142,035,833 bytes. Copyable
outer resources account for 32,583 bytes, also present inside the generated PHAR.
These measurements include all current runtime package files and are not a
claim of globally minimal packaging.

Real Composer local and isolated global ``require --prefer-dist`` install one
solver package and execute the ready binary. Repeating ``composer install
--no-dev --no-scripts --no-plugins`` in the local consumer succeeds with no
exported ``src``. ``--prefer-source`` also runs in the separate synthetic fixture
whose Git tree includes the artifact; that does not establish source support
for an asset-only publication.

A separate source-only local VCS fixture contains no PHAR. Its explicit ``dist``
URL points at the already-built ZIP. Local and isolated global dist installations
run successfully; source installation receives no PHAR and exits 255 when the
wrapper requires it. This is an actual Composer driver test, not a changed
Packagist record or published GitHub asset. The live publication integration
remains undecided.

Native GZ probes pass on PHP 8.4.27 and 8.5.12: real ECS diffs (exit 2), explicit
Rector LongArrayToShortArrayRector preview (exit 2), schema-backed normalization
with a stale-lock diagnostic (exit 1), and native Magic Numbers text/XML findings
(exit 1). Source, policy and Composer metadata bytes remain unchanged. The PHP
8.4 default scan directory contains broken unrelated PECL entries; the probes
use an empty process-local scan directory and record the loaded modules without
changing host configuration. ``php -n`` starts the binary on both installed PHP
builds; this does not establish compatibility with arbitrary minimal PHP builds.

Two simultaneous bounded checks use different private runtime directories,
remove both at completion and preserve fixture input bytes. Payload corruption
is rejected with exit 255 and leaves no extracted runtime. Ordinary shutdown
cleanup and paths with spaces pass. Abrupt process termination, restrictive
filesystem configurations and all consumer custom-autoload combinations are
not qualified.

PHP 8.3 PHAR execution, a minimal-extension build, production locked build-root
generation, reproducibility guarantees, artifact/version/ref publication ordering
and final production review remain gates. The production ``require`` and source
exports are unchanged. Choose the generated-artifact publication trade-off before
removing production dependencies, library autoload or exported ``src``.

Copyable resources
------------------

Changelog's canonical copying source is ``resources/workflows/changelog.yml``;
its ``WorkflowReleaseResolver`` and installation tests distinguish those caller
bytes from the executable ``.github/workflows/changelog.yml`` input contract.
Code Standards already has the analogous ``resources/workflows/code-standards.yml``
caller. Its executable reusables remain under ``.github/workflows``. The caller
references the implementation rather than manually duplicating it. Future DevTools
may copy these resources; all of ``resources`` is retained and archive tests protect
non-skill templates as well as the existing skills and licenses.
