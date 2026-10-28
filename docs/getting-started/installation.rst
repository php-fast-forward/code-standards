Installation and compatibility
==============================

The tool requires PHP 8.3 or later within the supported 8.x range, Git, and
Composer runtime API 2.2 or later. Contributor builds additionally need Bash, jq and Node.js 24 or later for
workflow contract tests. The release-channel selection fixtures run through
``node --test`` as part of ``composer test``. The locked contributor graph is tested on
PHP 8.3, 8.4 and 8.5.

For a source checkout of a reviewed commit:

.. code-block:: console

   composer install --no-plugins --no-scripts
   php bin/code-standards help

Use an actual published Composer version when adopting the package as a
``require-dev`` dependency. Check the repository releases first: documentation
of current source does not establish that a package version or the GitHub
``v0`` channel has been published. A local path repository is suitable for an
isolated trial; do not mistake its development constraint for a release.

The package's runtime dependencies are libraries, including Symfony Console,
Symfony Process, Fast Forward Container and ECS. The tool's ``require-dev``
dependencies are not installed transitively for consumers. CPX separates
installation and resolution, not PHP namespaces loaded into one process.

The executing PHP binary parses selected sources; it is not an emulator for a
consumer's older PHP minimum. No PHP version migration is enabled.
