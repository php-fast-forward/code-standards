Installation and compatibility
==============================

Install a verified published Composer version in the consumer Git project:

.. code-block:: console

   composer require --dev fast-forward/code-standards
   vendor/bin/code-standards list
   vendor/bin/code-standards check --all

Composer's binary proxy targets ``builds/code-standards`` directly. The package
contains a ready executable; consumers run no build and resolve no internal tool
libraries. PHP ^8.3, Git, and the extensions declared in ``composer.json`` are
required, including Phar and zlib. Native XML magic-number reports also need DOM.
The executing PHP parses selected sources; it does not emulate an older PHP.

Optional Composer scripts may invoke ``code-standards check --all`` and
``code-standards fix --all``. The latter requires explicit formatting authority.
Global use installs with ``composer global require fast-forward/code-standards``;
find its executable directory with ``composer global config bin-dir --absolute``.

Check the actual published artifact before adopting this distribution contract.
A source merge, draft PR or GitHub workflow channel does not prove a ready
Packagist distribution exists. See `PHAR distribution <../phar-distribution.rst>`__.
``--prefer-source`` may include source files in addition to a committed artifact;
source-only checkouts require contributor dependencies and a build.

Contributors retain the separate source entrypoint:

.. code-block:: console

   composer install --no-plugins --no-scripts
   php bin/code-standards help
   composer build

Build dependencies live in ``require-dev``. Builds resolve locked runtime roots
inside a disposable directory; they never consume Code Standards itself. Workflow
tests additionally need Bash, jq and Node.js 24 or later. The contributor graph
and compiled executable are tested on PHP 8.3, 8.4 and 8.5.
