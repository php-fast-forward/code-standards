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

These links lead to the maintained Markdown source records rather than claiming
that a documentation site or coverage report is published:

* `Architecture and bootstrap <https://github.com/php-fast-forward/code-standards/blob/main/docs/architecture.md>`_
* `Workflow and ref contract <https://github.com/php-fast-forward/code-standards/blob/main/docs/automation.md>`_
* `Policy comparison <https://github.com/php-fast-forward/code-standards/blob/main/docs/policy.md>`_
* `Resolved preset <https://github.com/php-fast-forward/code-standards/blob/main/docs/resolved-preset.md>`_
* `Composer dependencies <https://github.com/php-fast-forward/code-standards/blob/main/composer.json>`_

Runtime libraries include Console for input/help, Process for child execution,
ECS for formatting, Container for composition and its provider/PSR contracts.
Composer's runtime API supplies the installed binary proxy's autoload location.
The lock file records transitive versions; this documentation does not replace
that graph with a handwritten dependency catalog.
