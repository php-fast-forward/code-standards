References and dependency boundaries
====================================

* `Repository <https://github.com/php-fast-forward/code-standards>`_
* `Issues <https://github.com/php-fast-forward/code-standards/issues>`_
* `Releases <https://github.com/php-fast-forward/code-standards/releases>`_
* `ECS configuration <https://github.com/symplify/easy-coding-standard>`_
* `Symfony Console <https://symfony.com/doc/current/console.html>`_
* `Fast Forward Container <https://github.com/php-fast-forward/container>`_
* `Changelog lifecycle <https://github.com/php-fast-forward/changelog>`_

Source records
--------------

These links lead to the maintained source records rather than claiming
that a documentation site or coverage report is published:

* `Architecture and bootstrap <https://github.com/php-fast-forward/code-standards/blob/main/docs/architecture.rst>`_
* `Workflow and ref contract <https://github.com/php-fast-forward/code-standards/blob/main/docs/automation.rst>`_
* `Policy comparison <https://github.com/php-fast-forward/code-standards/blob/main/docs/policy.rst>`_
* `Resolved preset <https://github.com/php-fast-forward/code-standards/blob/main/docs/resolved-preset.rst>`_
* `Composer dependencies <https://github.com/php-fast-forward/code-standards/blob/main/composer.json>`_

Runtime libraries include Console for input/help, Process for child execution,
ECS for formatting, Container for composition and its provider/PSR contracts.
The explicit semantic command uses Rector with PHPStan as an engine dependency;
the `native refactoring guide <../rector.rst>`__ records the qualified dependency
versions and shared-graph compatibility restriction. No type-analysis command
is implied by that dependency.
Libraries are bundled inside the PHAR. The consumer dependency graph contains
only the tool package and declared PHP/platform requirements.
The lock file records transitive versions; this documentation does not replace
that graph with a handwritten dependency catalog.
