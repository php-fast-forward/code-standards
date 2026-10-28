Structured check output
=======================

``check --format=json`` selects one nonstreaming JSON report on stdout. The default
``--format=text`` retains human output and existing exit behavior. ``fix`` does not
accept this option. Formatting and semantic refactoring remain separate operations.

``--format=json-pretty`` selects the same report with indentation. The canonical
`result presentation guide <process-contracts.rst#structured-check-presentation>`__
defines both JSON representations, color behavior and encoding failures.

.. code-block:: console

   vendor/bin/code-standards check --all --format=json
   vendor/bin/code-standards check --base=BASE_SHA --head=HEAD_SHA --format=json

Use existing complete immutable commits for ``BASE_SHA`` and ``HEAD_SHA``. Selection,
consumer ignore rules, explicit ECS configuration and PHP runtime requirements are
the same as text checking. JSON checking never requests ``--fix``. Executable
configuration is not sandboxed; use the same unprivileged environment as text mode.

Report contract
---------------

The report includes ``schema_version`` (1), ``command`` (``check``), ``status``, ``exit_code``, ``complete``,
``selection``, ``files``, ``errors`` and ``diagnostics.stderr``. ``files`` is an object
keyed by exact absolute paths. Native ``errors`` records retain line, file path,
message and checker identity; native ``diffs`` retain diff text and applied checkers.
No line numbers are guessed from a diff or a human PHP diagnostic.

``selection.files`` counts selected files; ``syntax_checked`` counts completed
native PHP syntax invocations; ``style_submitted`` counts files submitted in
completed, consistently reported ECS batches. The corresponding ``syntax_remaining``
and ``style_remaining`` counts identify unfinished work. ECS may reuse its cache or
honor consumer skips, so submitted is not a claim of fresh parsing of every file.

``complete`` means all selected syntax invocations and ECS batches completed with
consistent reports. Checking stops at the first nonzero syntax result, findings
batch or operational error, matching the bounded text-mode policy. A findings
report can be incomplete when later batches remain. An empty selection is complete
and clean without starting a checker.

.. list-table:: Result states
   :header-rows: 1

   * - Status
     - Exit
     - Meaning
   * - ``clean``
     - 0
     - All selected work completed consistently without findings, or selection is empty.
   * - ``findings``
     - 2
     - Native, consistently reported style violations or diffs; check ``complete``.
   * - ``syntax-failed``
     - Native nonzero status
     - ``php -n -l`` did not validate a selected file; exact diagnostics are data, not parsed classifications.
   * - ``operational-error``
     - 1, or 2 for controlled selection failures
     - Selection, execution, report validation or encoding could not establish a trustworthy result.

Controlled selection failures retain text-mode status 2, including invalid input,
missing configuration, unavailable Git history, a mismatched HEAD or a dirty tracked
checkout. Unexpected failures and checker/report errors use status 1.

Native stderr remains separate diagnostic data and is also written to stderr;
warnings alone do not imply failure. Operation errors additionally produce a fixed
stderr diagnostic. Invalid format names fail before execution with status 2 and
stderr output. Symfony binding/help errors and global quiet/silent flags retain
their native Console behavior; they are not JSON-report requests to an executed
check.

Validation and limits
---------------------

The isolated worker uses the tool's own autoloader and native ECS JSON formatter.
Custom process overrides must implement the optional ``LimitedResultProcessRunner``
capability for JSON checking. Existing ``ProcessRunner`` implementations retain
their text-mode contract; an unsupported JSON transport fails before a checker starts.
The parser checks the whole JSON document, native status, record shapes, selected
paths and counts. It does not strip banners or search for a later JSON fragment.
ECS totals can include system errors absent from its file inventory; mismatches
are operational errors, never clean results or ordinary style findings.

Each ECS batch contains at most 50 paths, with a 300-second execution timeout,
2 MiB retained stdout and 64 KiB retained stderr limits. Syntax invocations use
300 seconds and 64 KiB per channel. Combined retained diagnostics are limited to
64 KiB. Output is limited during native process callbacks; overflow fails instead
of returning a truncated report. Symfony's transient pipe reads and the child's
memory are outside this retained-output bound. Termination covers the owned
process; no sandbox or descendant-process isolation is promised.

Report strings must be representable as JSON without UTF-8 replacement. Invalid
native JSON, unsupported bytes or an oversized report fail objectively. A complete
machine report can be larger than human output because ECS JSON contains diffs;
``--no-diffs`` is not used as a false size guarantee. Native caches and explicit
consumer overrides remain active, as described in the configuration contract.

This format covers checking, not automatic fixes, Rector reports, type analysis or
agent detection. Select it explicitly; environment detection never grants write
authority or overrides the requested format.

The broader `structured-output contract (#10) <https://github.com/php-fast-forward/code-standards/issues/10>`__
also covers cross-command color controls and CWD/cache metadata. Those fields and
cross-command policies are outside this check schema.
