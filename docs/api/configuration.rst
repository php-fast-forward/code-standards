API and supported extensions
============================

The supported preset extension is
``FastForward\CodeStandards\Config\ECSConfig::configure()``. It returns the
native ``ECSConfigBuilder`` and applies the optional callback last. There is no
second configuration schema or automatic consumer-config discovery.

.. code-block:: php

   <?php

   declare(strict_types=1);

   use FastForward\CodeStandards\Config\ECSConfig;
   use PhpCsFixer\Fixer\Phpdoc\PhpdocAlignFixer;
   use Symplify\EasyCodingStandard\Configuration\ECSConfigBuilder;

   require __DIR__ . '/vendor/autoload.php';

   return ECSConfig::configure(static function (ECSConfigBuilder $config): void {
       $config->withConfiguredRule(PhpdocAlignFixer::class, ['align' => 'left']);
   });

Save the example as ``ecs.php`` in a disposable consumer, then select it with
``--config=ecs.php``. A configuration file is executable PHP and must be trusted
at the privilege level of its process. Invalid paths are rejected before ECS
runs; native configuration errors propagate from ECS.

Internal services
-----------------

.. list-table:: Composition responsibilities
   :header-rows: 1
   :widths: 35 65

   * - Component
     - Responsibility
   * - Console command objects
     - Invocable POPOs with AsCommand and mapped option attributes.
   * - ProjectScopeResolver
     - Validate mode, project directory and explicit configuration path.
   * - FileSelection / PhpFiles
     - Select Git destinations and enforce the owned PHP scope.
   * - StandardsRunner / ProcessRunner
     - Run bounded syntax/style processes and preserve their statuses.

These services describe current implementation, not a compatibility promise
for every internal constructor. Symfony Console owns binding and help; the
published Fast Forward Container adapter owns composition and autowiring.
