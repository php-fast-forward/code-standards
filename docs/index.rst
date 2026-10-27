Fast Forward Code Standards
===========================

Code Standards owns PHP formatting, syntax validation and exact-PR file selection.
It provides a native ECS preset, explicit check/fix/refactor commands and unprivileged
GitHub Actions. It does not run application tests, type analysis, coverage or
semantic migrations in a consumer check.
The separate opt-in ``magic-numbers`` command analyzes numeric literals without
changing source or widening the formatting defaults.

.. toctree::
   :maxdepth: 2

   getting-started/index
   usage/index
   api/index
   advanced/index
   links/index
   faq

Architecture, policy and evidence
---------------------------------

.. toctree::
   :maxdepth: 1

   architecture
   automation
   instruction-map
   legacy-fixer-comparison
   magic-numbers
   phpdoc-comparison
   policy
   rector
   resolved-preset
   static-analysis-evaluation
   structured-check
   twig-evaluation
