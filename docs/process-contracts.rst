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
