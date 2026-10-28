Optional Composer metadata normalization
========================================

``normalize-composer`` checks the current project's ``composer.json`` in memory.
It is opt-in and independent of ``check``, ``fix``, ECS and changed-PHP selection.
No Git checkout is required. Neither the default Composer scripts nor Actions
enable it automatically.

.. code-block:: sh

   vendor/bin/code-standards help normalize-composer
   vendor/bin/code-standards normalize-composer
   vendor/bin/code-standards normalize-composer --directory=/path/to/project
   # With explicit authority to change Composer metadata:
   vendor/bin/code-standards normalize-composer --apply
   git diff -- composer.json composer.lock
   composer validate --strict --no-plugins --no-scripts

Options and effects
-------------------

``--directory`` (``-C``) selects an existing project directory; the default is
the current directory. ``--apply`` defaults to false. Without it the command
reads metadata and reports whether normalization is needed, without writing
source, metadata, lock, vendor, caches or generated artifacts. Standard Symfony
global options appear in installed help. There are no file-selection, ECS
configuration, lock-update, dependency-update or plugin-activation options.
The operation uses the root ``composer.json`` and optional root ``composer.lock``;
missing/unreadable manifest files and symlink metadata files are rejected.
Invalid JSON diagnostics omit the rejected bytes, including in verbose output,
so malformed metadata cannot disclose embedded URLs or tokens through errors.

Apply writes only ``composer.json``. Inspect its diff even on failure: a failed
write can be partial, and a successful write can make the existing lock stale.
An initially stale lock prevents writes. After writing, the command checks the
new manifest against the original lock and reports an incompatible hash with
status 1. It never edits the hash, resolves packages or runs a lock update.
Review any subsequent lock maintenance as a separate dependency operation.
Legacy locks with only ``hash`` use Composer's legacy exact-byte comparison.

Exit codes
----------

* ``0``: the manifest is normalized (or was explicitly normalized), and an
  existing lock hash is fresh. No lock is required.
* ``1``: check proposes normalization, the initial lock is stale, or explicit
  application left a stale lock. Read the diagnostic to distinguish these cases.
* ``2``: invalid options/directory, missing/unreadable/symlink files, invalid
  JSON/schema or controlled filesystem/library failures.

Normalization and dependency boundary
-------------------------------------

This integration uses ``ergebnis/json-normalizer`` 4.11.0, the library used by
the verified ``ergebnis/composer-normalize`` 2.54.0 release. ``composer/composer``
2.10.3 supplies the local schema and public ``Locker::getContentHash()`` API.
The installed schema has the same property definitions/order as that release's
schema. Library dependencies are normal runtime requirements; no Composer plugin
is added or enabled, and ``config.allow-plugins`` is unchanged.

This is a conservative metadata profile, rather than the plugin's complete CLI.
It retains every original dependency constraint string in ``require``,
``require-dev``, ``conflict``, ``provide`` and ``replace``; the upstream CLI also
rewrites those strings. Package keys and schema properties are normalized.
If upstream would canonicalize a dependency key, or the section contains
case-colliding names, the adapter retains the entire original section's keys,
values and order instead; this preserves package/extension-name aliases and
their effective Composer constraints. Suggested package
keys/descriptions receive the same protection.
Sniffed indentation, newline style and presence of a final newline are retained.
The plugin's ``extra.composer-normalize`` indentation overrides and CLI flags
are not consumed. There is no automatic formatting policy migration.

Deliberate differences from the upstream plugin CLI also include:

* Check is the default; upstream requires ``--dry-run`` to avoid writes.
* Apply never maintains locks; upstream defaults to updating them unless
  ``--no-update-lock`` is selected. This command offers no lock-update escape.
* Only root metadata filenames are accepted, with symlinks rejected; there is
  no arbitrary manifest argument or ``--no-check-lock`` switch.
* Check emits a normalization diagnostic, without the plugin's unified diff.
  Inspect explicit application with Git; neither output nor flags are CLI-compatible.
* Controlled JSON/schema/input failures use status 2; upstream normalization
  validation generally uses status 1. The statuses in this guide are authoritative.

The adapter restores ``allow-plugins`` and ``preferred-install`` policies exactly,
including broad-first wildcard rules: upstream sorts some of these maps by
specificity, which can change Composer's first-match policy. Native normalization
retains repository priority (arrays and named objects), script command lists,
autoload file/path lists, installer paths and patch order. Regression fixtures assert these boundaries,
literal aliases/OR constraints, idempotence and complete file-byte preservation
in check mode. Review arbitrary extension metadata in the resulting diff.

After CLI bootstrap, normalization uses only data APIs. It never constructs a
Composer application, plugin manager or installer, starts Composer, discovers an
additional target-project autoload/config, executes Composer script commands or
fetches a remote schema. In an ordinary consumer Composer installation, the
binary proxy intentionally loads the shared consumer/tool autoloader: registered
autoload files can execute during that existing bootstrap. A separate tool
installation does not import the target project's vendor/autoload.php; the
poison-autoload fixture verifies that separate-installation case. See the
`architecture contract <architecture.rst>`__. This is not a filesystem sandbox
and does not support concurrent edits to the same metadata.

Dependency cost and alternatives
--------------------------------

The verified graph adds eight packages for ``ergebnis/json-normalizer`` and
its Composer constraint-parser support. This is the official standalone
normalization library: the plugin itself is not a runtime dependency.
Using it avoids copying its schema traversal, priority exclusions and native
formatting implementation into this project.

Another fifteen new packages come from ``composer/composer``. They support the
official schema and ``Locker::getContentHash()`` API; they are not all needed
by the in-memory normalizer. This is an intentional cost of preserving native
lock-freshness validation without maintaining a second hash algorithm. Existing
``psr/log`` and ``symfony/filesystem`` also become runtime dependencies, retaining
their locked versions. No existing package version is updated in this change.

The smaller alternative is the standalone normalizer plus ``composer/semver``
and a copied official schema. It would still need a separate lock-hash
implementation or weaker lock validation: neither is provided by the reviewed
standalone normalizer API. The Composer plugin would require consumer plugin
policy changes; invoking it manually would defeat that boundary. A PHAR could
isolate its package graph but would introduce artifact verification, packaging
and a second runtime/distribution lifecycle. This increment selects the native
library/hash APIs rather than those additional contracts.

Library autoloading is still executable code. The newly added graph includes
React Promise's conditional function include and Symfony's PHP polyfill
bootstraps. These were inspected in the resolved releases; they define helper
functions/constants, with version-gated polyfill setup, and do not install or
activate Composer plugins. No newly added package has ``composer-plugin`` type.
These files load with the tool autoloader even when normalization is not chosen;
this integration does not claim namespace or runtime isolation. Composer must
resolve compatibility with a normal consumer installation.

The direct constraints use caret ranges (``^4.11.0`` normalizer,
``^2.10.3`` Composer, ``^1.7.0`` JSON and ``^3.9.1`` printer), allowing compatible
minor/patch updates up to the next major. The lock records the exact verified
versions; consumers resolve their own graph within those ranges. The minimum
versions and current locked graph are tested; this does not claim execution of
unreleased versions admitted by those ranges.
Schema content/order therefore belongs to the installed Composer package.
There is no runtime schema updater, remote-schema fallback, download or global
installation. Dependency updates require reviewing the schema, autoload files,
significant-order fixtures, constraint preservation and lock-hash behavior again.

References
----------

* `composer-normalize 2.54.0 <https://github.com/ergebnis/composer-normalize/tree/2.54.0>`__
* `json-normalizer 4.11.0 <https://github.com/ergebnis/json-normalizer/tree/4.11.0>`__
* `Composer 2.10.3 <https://github.com/composer/composer/tree/2.10.3>`__
