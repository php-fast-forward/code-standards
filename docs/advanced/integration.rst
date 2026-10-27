Integration and troubleshooting
===============================

GitHub Actions
--------------

``resources/workflows/code-standards.yml`` is the consumer wrapper. It calls the
public reusable workflow at ``@v0`` only after that released channel exists.
A reviewed immutable commit is another adoption choice. GitHub refs and Composer
constraints are different selections.

The consumer wrapper triggers the reusable through ``workflow_call`` on
``pull_request`` events. The reusable runs on ephemeral hosted Ubuntu runners
with read-only contents access. It checks out the exact consumer head and
installs only the tool's locked dependencies with plugins/scripts disabled.
It never runs the formatter in fix mode and accepts no consumer secrets.
Never move this check into a privileged pull_request_target job.

The helper's checkout is bound to the called job's validated workflow identity,
not a second moving-ref lookup. Hosts missing the required native identity
metadata fail closed. See the detailed source workflow contract in the links.

Agent skill consumption
-----------------------

The canonical consumer procedure is shipped at
``resources/skills/fast-forward-code-standards/SKILL.md`` and advertised through
Composer's ``extra.skills.source``. Consumers may explicitly choose
``llm/skills`` to discover and synchronize it. Installing this library does not
activate an agent skill or migrate consumer settings. A project's reviewed
skills configuration controls donors and destinations; no parallel installer is
provided by Code Standards.

Cache and overrides
-------------------

The default ECS cache lives under the system temporary directory, isolated by
a SHA-256 of the executing checkout path. A native ``withCache()`` override is
supported and applies last. Sharing a cache requires coordinating its
invalidation and concurrency; check mode never promises absence of temp caches.

Composition overrides belong at the Container boundary before first service
lookup. Use the published Container's provider contracts rather than a parallel
registry. The architecture source documents the locked adapter and lazy,
shared-service behavior. This package does not offer a custom DI DSL.

Local documentation build
-------------------------

The guides use reStructuredText and the Fast Forward phpDocumentor template.
Run the PHP DevTools documentation command from the project root, using an
externally provisioned DevTools installation and template:

.. code-block:: console

   /path/to/dev-tools/bin/dev-tools reports:docs \
       --source=docs \
       --target=/tmp/code-standards-docs \
       --cache-dir=/tmp/code-standards-phpdoc-cache \
       --template=/path/to/phpdoc-bootstrap-template

The native command derives API sources from Composer autoload metadata and
renders guides with phpDocumentor. DevTools and the template are documentation
build tools; they are not runtime dependencies of this package. The output and
cache paths must be writable and dedicated to this build. No Python renderer or
project-specific documentation engine is required.

This command builds local HTML. Hosting, coverage and application reports are
separate operations; it does not configure a deployment workflow.
