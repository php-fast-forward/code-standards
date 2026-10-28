Explicit semantic refactoring
=============================

The opt-in ``refactor`` command previews native semantic transformations and applies them only when explicitly requested. It does not select a migration profile or authorize publication. ``check`` and ``fix`` never invoke the semantic runner. The separate invocable command uses the existing container factories and process transport.

The manifest requires Rector ``^2.8.2`` and PHPStan ``^2.3.3``. Rector 2.4.2 accessed PHPStan's internal ``RichParser::$container`` property, which is absent in newer PHPStan versions; that older combination failed before rule execution. Rector 2.8.2 creates its parser through a native factory compatible with PHPStan 2.3.3. The minimum versions advance together so consumers cannot resolve the older incompatible Rector with newer PHPStan. Both constraints permit subsequent compatible minor releases. PHPStan is an engine dependency here, not a newly exposed analysis command. Dependency updates require the real integration fixtures below. Native configuration is the only extension point; no PHP parser, configuration translator or engine framework is introduced. Configuration paths resolve from ``--directory`` or the current directory. This command deliberately uses the configuration's whole-project paths rather than borrowing the formatter's changed-file list.

.. code-block:: php

   <?php

   use Rector\Config\RectorConfig;
   use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
   use Rector\ValueObject\PhpVersion;

   return RectorConfig::configure()
       ->withPhpVersion(PhpVersion::PHP_74)
       ->withPaths([__DIR__ . '/src'])
       ->withRules([LongArrayToShortArrayRector::class])
       ->withCache(sys_get_temp_dir() . '/my-project-rector/' . hash('sha256', __DIR__))
       ->withoutParallel();

The target is a required consumer decision, independent of the host PHP. Declare it with native ``withPhpVersion()`` or ``RectorConfig::phpVersion()``, directly or in an imported native configuration. Composer constraints and Rector's runtime fallback do not satisfy this explicit target requirement. ``withPhpVersion`` limits supported transformations; it does not downgrade existing code or select PHP migration sets. Review any later ``withPhpSets`` selection separately. The command does not infer or overwrite target, rule, cache, bootstrap, parallelism or path choices. Configure generated caches separately from owned source, with a directory unique to the checkout. No consumer dependency/lock update occurs. Rector 2.8.2 uses its configured file cache in CI as well as local runs. The command preserves that native selection; generated cache files are separate from consumer-owned source, configuration and lock files.

.. code-block:: console

   php bin/code-standards refactor --config=rector.php --directory=/project
   # After reviewing the proposed semantic diff:
   php bin/code-standards refactor --config=rector.php --directory=/project --apply
   # For a larger project, choose an explicit finite per-subprocess budget:
   php bin/code-standards refactor --config=rector.php --directory=/project --timeout=900

Preview passes native ``--dry-run``. Explicit apply performs one write pass and then a dry-run verification; remaining differences fail. Native exits are 0 for clean/success, 1 for processing errors or subprocess timeouts and 2 for proposed changes. Controlled invocation, empty-rule and invalid-inventory errors also return 2, with an error diagnostic. Other native fatal exits propagate unchanged. Rector 2.8.2 uses ``-n`` for dry-run and does not expose the ECS ``--no-interaction`` option; its native flags are not translated blindly.

``--timeout`` accepts a positive whole number of seconds and defaults to 300. It bounds each Rector subprocess separately: preflight inventory, processing and post-apply verification receive the same limit. Fractional, negative, zero, nonfinite and overflowing values are rejected; no unlimited mode is exposed. The option changes this refactor invocation, leaving Git's 120-second captured transport default and formatting's 300-second default unchanged. Native Rector process/parallel-worker limits remain native configuration decisions.

Native JSON preview
-------------------

``--format`` accepts ``text`` (the default) or ``json``. JSON selects Rector's
native output formatter for preview, preserving its totals, errors,
``changed_files`` and ``file_diffs`` without scraping or rewriting them.
JSON and ``--apply`` are rejected together before evaluating configuration or
starting native work: apply plus verification would otherwise concatenate two
documents. Text apply and its verification keep the existing behavior.

.. code-block:: console

   php bin/code-standards refactor --config=rector.php --format=json > rector-preview.json

The redirection writes only the explicitly selected report file. Native JSON
has ``totals.changed_files`` and ``totals.errors``; available processing errors
carry file/message/line fields. Proposed changes carry files and native diffs,
not an AST-location field. The reusable does not infer source lines from diff
text. Clean preview returns 0; native changes return 2; processing errors
return 1. Controlled invocation errors can also return 2, so automation must
validate the complete report together with its exit status. An unavailable or
malformed report is an operational error, never a clean or advisory finding.

This representation adds no inventory or processing invocation. Executable
configuration and its stdout can still affect execution/report validity; JSON
preview retains the trust and namespace boundaries below. The
`optional workflow guide <optional-analysis.rst>`__ describes explicitly enabled
Rector preview, report/fail policies and bounded summaries.

Executable configuration and initialization boundary
----------------------------------------------------

The command first asks native ``list-rules --output-format=json`` for the selected configuration's rules. A temporary, tool-owned native PHP wrapper imports that configuration into Rector's own initialized container and checks its public parameter API for an explicit PHP target. After native rule skips are applied, Rector's own ``ConfigInitializer::areSomeRectorsLoaded()`` must confirm an effective rule before inventory loading. This rejects an empty configuration even when native service discovery lists an unconfigured helper rule. The wrapper is removed after preflight, including failures; selected source and consumer configuration are not rewritten. The actual ``process`` commands keep the original config path and cache identity. Missing or invalid native targets fail without semantic writes. An empty or invalid inventory is rejected without calling ``process``: native Rector otherwise can generate ``rector.php`` and return success even during dry-run. Missing explicit configuration is rejected before launch.

This is a contract for **trusted, deterministic configuration**. Preview runs one inventory and one processing invocation; successful apply adds a verification invocation. Native Rector may evaluate configuration multiple times within an invocation. The guard does not add another configuration-evaluation process. A configuration that changes its target or rules between invocations can defeat that preflight. Arbitrary configuration, Composer autoload files, custom rules and configured bootstraps can execute and mutate files even in dry-run. The command is not a filesystem or security sandbox, and cannot promise read-only behavior for such executable extensions. Do not use them with privileged events, secrets or an untrusted consumer. Changes to the native initialization contract require regression review before changing the pinned Rector version.

The subprocess prevents Rector from sharing the parent CLI's in-memory state. It does not guarantee namespace isolation in the child: Rector loads its installation autoloader and may load the consumer's ``vendor/autoload.php`` too. The collision regression requires failure rather than a false clean result; configuration/bootstrap compatibility remains a consumer qualification gate. Captured preflight and streamed processing use the same credential stripping. No generic promise of semantic safety follows from a zero result.

The integration fixtures cover no-write preview, explicit apply/verification, empty custom configuration, missing/invalid targets, a PHP 7.4 target under a newer host, imported targets and native service bindings, configured cache ownership, configuration context/evaluation counts, native bootstrap failures and consumer/tool autoload collisions. Run the consumer's affected behavioral tests and review API/side-effect changes before accepting any semantic patch. Type analysis and application testing are not implicitly added to this command.
