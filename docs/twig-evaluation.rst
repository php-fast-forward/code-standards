Native Twig evaluation
======================

This contributor evaluation qualifies one formatter, Twig-CS-Fixer, together
with Twig's native syntax parser. It does not expose a Code Standards Twig
command, install anything during a check, or establish a supported consumer
profile. It covers the evaluation portion of `issue #9
<https://github.com/php-fast-forward/code-standards/issues/9>`_; that issue's
broader command, compatibility and acceptance criteria remain separate.

The nested Composer lock selects published Twig-CS-Fixer 4.1.1, source
``53c1234b6e5d87ac2beaf686774bba013697f9c5``, and Twig 3.30.0, source
``8c737079b726af72ff8ef3c595be9f6a810ea1ef``. The evaluator verifies those actual
installed metadata records. Its graph is separate from the package's runtime
and contributor dependencies. The resolution platform is PHP 8.3.0; the existing
repository CI matrix executes the evaluation on PHP 8.3, 8.4 and 8.5.

Explicit provisioning
---------------------

Dependency provisioning is a separate operation. The evaluator requires an
existing absolute ``vendor/autoload.php`` outside the checkout and fails with
an objective diagnostic when it is absent. It never invokes Composer. CI places
the isolated vendor, Composer home and cache under ``runner.temp`` and disables
plugins and scripts during installation.

For a local checkout, provision into a newly owned external directory:

.. code-block:: console

   twig_evaluation_runtime="$(mktemp -d "${TMPDIR:-/tmp}/standards-twig.XXXXXX")"
   COMPOSER_HOME="$twig_evaluation_runtime/home" \
   COMPOSER_CACHE_DIR="$twig_evaluation_runtime/cache" \
   COMPOSER_VENDOR_DIR="$twig_evaluation_runtime/vendor" \
     composer install --working-dir=tests/Evaluation/Twig --no-dev --no-plugins --no-scripts --no-interaction --prefer-dist --no-progress
   TWIG_EVALUATION_AUTOLOAD="$twig_evaluation_runtime/vendor/autoload.php" \
   TWIG_EVALUATION_TEMP="$twig_evaluation_runtime" \
     php tests/Evaluation/Twig/evaluate.php

The harness creates and removes only its own randomly named synthetic fixture.
It preserves the caller-provisioned runtime. No real consumer checkout is
analysed or changed by this evaluation.

Qualified path and rule contracts
--------------------------------

``ConfigResolver::resolveConfig([], $configPath, true)`` preserves the native
consumer Finder and disables the native cache. Explicit native CLI paths can
replace that Finder. The harness reproduces that gap and tests the narrower
integration: iterate the complete configured Finder result, then intersect it
with the Git-eligible candidate identities and materialize a sorted, deduplicated
file list. This includes appended Finder iterables, which do not necessarily
pass its directory filters. Configuration cannot expand the selected scan.

The owned fixture covers Finder exclusions, Git-ignored force-added files,
unselected and outside-appended files, duplicates, spaces and newline-bearing
paths. Git's NUL-delimited output is retained. These assertions qualify the
native integration boundary; they do not introduce a second Git diff parser.

The fixture selects native ``Standard\Twig`` explicitly, rather than silently
adding all rules from ``Standard\TwigCsFixer`` or a framework standard. Consumer
``overrideRule()`` and ``removeRule()`` remain authoritative. ``addRule()`` can
retain distinct configurations of the same class, so it is not treated as a
universal last-wins replacement. Owned defaults are not reapplied after an
explicit consumer configuration.

Syntax, writes and reporting
----------------------------

Native style checks alone do not establish complete Twig grammar validity.
The test worker first calls native ``Environment::tokenize()`` and ``parse()``
for all effective files. Grammar failure prevents any fix pass. The parser
receives the selected native Config's extensions, token parsers and node
visitors. A custom tag and registered application function parse successfully;
the function is not invoked and a missing include is not loaded. An unregistered
custom tag fails. No template is compiled or rendered.

The style pass uses native ``Linter``, ``Tokenizer`` and an explicit ``Fixer``
only for the requested write case. Check preserves source/configuration/lock
bytes; explicit fix is followed by check and a second idempotent fix. Native
JSON comes directly from ``GitlabReporter`` into a fresh output buffer, without
scraping ANSI or human-output prefixes. Every result gets a new reporter because
the native object retains fingerprint collision state. Repeated checks produce
byte-identical JSON, including escaped control characters in relative paths.
The native report's error/fatal count determines failure, independently of
presentation thresholds.

Limits
------

Configuration and registered extension PHP are explicitly trusted executable
code. Parsing can execute token parsers and node visitors; absence of rendering
is not a sandbox guarantee. The native Config resolver does not automatically
import the consumer's ``vendor/autoload.php`` in the qualified version. A
sentinel regression checks that boundary; an explicit configuration can still
load an application intentionally.

Twig-CS-Fixer Config 4.1.1 exposes extensions, token parsers and node visitors, not an arbitrary
complete application Environment, custom lexer, loader or environment options.
Those full-environment integrations are unsupported by this evaluation. Unknown
application functions and tags need native registration. The style engine's
stubbed environment is not substituted for the explicit syntax environment.
Parsing with Twig 3.30.0 does not prove compatibility with an older consumer Twig
minimum or the existence of imported application resources.

Twigcs remains an overlapping style comparator, not a second mandatory pass.
The evaluated standalone twig-linter package has incompatible Symfony constraints
and is not added. This bounded strategy avoids three mandatory engines while
preserving the unresolved consumer-profile decisions in issue #9.

Implementation references
-------------------------

- `Native Config resolution and Finder precedence
  <https://github.com/VincentLanglet/Twig-CS-Fixer/blob/53c1234b6e5d87ac2beaf686774bba013697f9c5/src/Config/ConfigResolver.php>`_
- `Native rule overrides
  <https://github.com/VincentLanglet/Twig-CS-Fixer/blob/53c1234b6e5d87ac2beaf686774bba013697f9c5/src/Ruleset/Ruleset.php>`_
- `Native JSON reporter
  <https://github.com/VincentLanglet/Twig-CS-Fixer/blob/53c1234b6e5d87ac2beaf686774bba013697f9c5/src/Report/Reporter/GitlabReporter.php>`_
- `Twig Environment parsing API
  <https://github.com/twigphp/Twig/blob/8c737079b726af72ff8ef3c595be9f6a810ea1ef/src/Environment.php>`_
