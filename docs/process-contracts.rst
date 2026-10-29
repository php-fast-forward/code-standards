Process transport contracts
===========================

``ProcessRunner`` distinguishes successful output capture from command status.
``ResultProcessRunner`` optionally adds separate raw output channels and status.

Normative language
------------------

Uppercase ``MUST``, ``MUST NOT``, ``SHOULD``, ``SHOULD NOT`` and ``MAY`` follow
`RFC 2119 <https://www.rfc-editor.org/rfc/rfc2119>`__ and
`RFC 8174 <https://www.rfc-editor.org/rfc/rfc8174>`__; lowercase words retain
their ordinary meaning. Use requirement terms sparingly for observable contracts.
Interface descriptions state semantic obligations, not implementation details.

Choosing an operation
---------------------

=================== =========================== =============================
Operation           Return value                Completed nonzero exit
=================== =========================== =============================
``capture``         Successful stdout           Raises an exception
``run``             Completed exit status       Returned unchanged
``captureResult``   Status, stdout and stderr   Returned as data
=================== =========================== =============================

Use ``capture`` for output consumed as data, such as a Git path list or Rector
JSON inventory. Use ``run`` to propagate tool status, with diagnostics permitted.
Use ``captureResult`` for raw stdout/stderr and status without streaming; a
nonzero status remains data. Failure before completion raises an exception.

.. code-block:: php

   $paths = $process->capture(['git', 'ls-files', '-z'], $directory);
   $status = $process->run([PHP_BINARY, '-n', '-l', $file], $directory);

   if (!$process instanceof \FastForward\CodeStandards\Process\ResultProcessRunner) {
       throw new \RuntimeException('This transport does not support result capture.');
   }

   $result = $process->captureResult([PHP_BINARY, '-n', '-l', $file], $directory);
   // Interpret $result->exitCode, stdout and stderr according to the selected tool.

The examples assume an injected transport, existing working directory and
selected source file. Check the optional capability before using an arbitrary
``ProcessRunner`` override. Result capture does not parse ECS/Rector reports.

Structured check presentation
-----------------------------

``check --format=json`` writes one compact versioned JSON document to stdout.
``check --format=json-pretty`` writes the same document with indentation. The
default format remains ``text``. Both JSON formats use the same read-only
selection and execution, preserve check exit codes and keep operational
diagnostics on stderr. They never add terminal color, including with ``--ansi``;
control characters inside diagnostic strings remain escaped JSON data.

.. code-block:: console

   cpx fast-forward/code-standards check --all --format=json
   cpx fast-forward/code-standards check --all --format=json-pretty

The schema contains ``schema_version``, ``command``, ``status``, ``exit_code``,
``complete``, selection coverage, native ECS ``files``, operational ``errors``
and stderr ``diagnostics``. Empty ``files`` is an object in both representations.
The renderer preserves paths, diagnostic strings and native findings without
parsing human output. Invalid UTF-8 produces an ``operational-error`` document
with exit 1 and retained coverage instead of silently replacing bytes.

Clean checks exit 0; style findings exit 2. Syntax failures retain the native
syntax exit. Unsupported formats and selection errors exit 2; operational
execution or encoding failures exit 1. A failing or partial check is never
represented as clean. JSON presentation grants no source-write authority and
does not change CWD or cache policy.

These presentation modes belong to ``check``. ``refactor --format=json`` keeps
its native Rector preview report and native exit codes; it does not acquire
``json-pretty`` or a check schema. Semantic changes still require explicit
configuration and ``--apply`` with text output.

The backed ``Output\OutputFormat`` enum centralizes the public presentation
identifiers ``text``, ``json`` and ``json-pretty``. Each command keeps its own
supported subset and schema. Native inventory formats and worker protocols
retain their separate meaning. Local ``CheckResult`` construction uses named
arguments for every supplied field so that coverage counts and completion
flags remain explicit.
``OutputFormat::values()`` derives values from ``cases()``. Native shell completion
calls the documented option callbacks to suggest each command's supported values. Suggested
values assist completion; explicit format validation still rejects unsupported
values before native work and preserves the established diagnostics and exits.

Native transport
----------------

``NativeProcessRunner`` uses Symfony Process. It rejects invalid timeouts before
launch and keeps the parent's working directory unchanged. Declared defaults
are 120 seconds for ``capture`` and 300 for ``run``/``captureResult``.

Its ``run`` forwards raw stdout/stderr chunks to the optional sink; without one
it is silent. Capture operations do not use the sink. Symfony retains buffers,
so sink delivery is not a bounded-memory guarantee. Execution and sink exceptions
can propagate.

The native child environment excludes ``GH_TOKEN``, ``GITHUB_TOKEN`` and
``COMPOSER_AUTH``. This is not a sandbox or an empty environment. Callers own
command authority: an explicitly selected shell, application or configuration
can execute code and change files. Capturing output does not make it read-only.

Preservation and extension
--------------------------

Golden fixtures exercise descriptive contracts and inheritance markers through
read-only checking, explicit fixing and idempotence. A native Rector regression
changes array syntax while retaining the same documentation. Consumer-selected
semantic rules can change PHPDoc; this is not a claim about every configuration.

Native Rector report validation
-------------------------------

``Refactoring\RectorJsonReportParser::parse()`` validates a completed
``ProcessResult`` against the native Rector preview schema and returns the
decoded native report. Totals, native lists, error locations, diff paths and
the exit code must agree. Malformed or inconsistent reports raise
``InvalidArgumentException`` with the established native validation diagnostic.
Optional fields and native record ordering remain intact, including absent
empty lists and opaque diff text. No source line is inferred from a diff.

The caller owns capture budgets. The optional workflow keeps its existing
report-size limit before parsing, then maps validated native data into its
existing statuses, diagnostic presentation and ``report``/``fail`` policies.
The parser does not execute Rector or evaluate configuration. The public
``refactor --format=json`` command continues to stream its native preview
output with the same options and exits.
