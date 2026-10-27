Process transport contracts
===========================

``ProcessRunner`` separates successful output capture from diagnostic execution.
``ResultProcessRunner`` is an optional capability for callers that need both
channels and the native exit status. These interfaces do not select a formatter,
refactoring policy or application bootstrap.

Normative language
------------------

Uppercase requirement words in these contracts follow
`RFC 2119 <https://www.rfc-editor.org/rfc/rfc2119>`__ and
`RFC 8174 <https://www.rfc-editor.org/rfc/rfc8174>`__:

* ``MUST`` and ``MUST NOT`` express a requirement and a prohibition.
* ``SHOULD`` and ``SHOULD NOT`` allow justified exceptions after their
  consequences have been assessed.
* ``MAY`` identifies optional behavior.

Lowercase words keep their ordinary meaning. Use these terms sparingly for
interoperability and consequential behavior, rather than turning every sentence
into a requirement. A method summary explains its purpose; subsequent paragraphs
describe semantics that parameter and return types cannot convey. Parameter,
return and exception tags add useful details instead of replacing the summary.

Choosing an operation
---------------------

The output-delivery column describes ``NativeProcessRunner``. The operation
purposes and return/failure distinctions follow the interface contracts; custom
overrides document their own delivery and exception details.

=================== =========================== ============================= =============================
Operation           Return on completion        Completed nonzero exit        Output delivery
=================== =========================== ============================= =============================
``capture``         Successful stdout bytes     Raises an execution failure   Buffered result; no live sink
``run``             Native integer exit status  Remains a nonzero status      Configured sink, when present
``captureResult``   ``ProcessResult``           Remains result data           Separate stdout/stderr bytes
=================== =========================== ============================= =============================

Use ``capture`` for a Git NUL-delimited path list or a successful Rector JSON
inventory: a failed command must not be mistaken for valid output. The transport
does not trim or decode stdout. Stderr is not concatenated into that return value.

Use ``run`` for syntax, formatter or semantic-tool diagnostics when the caller
needs the exit code and its configured output boundary. The native provider
connects the sink to Console output, preserving stderr when the output supports
it. A directly constructed ``NativeProcessRunner`` with no sink stays silent.
Chunks retain bytes, but no total order between stdout and stderr is promised.
Streaming to a sink does not imply bounded memory: Symfony also retains buffers.
Exceptions from a configured sink may propagate instead of producing a status.

Use ``captureResult`` when nonzero statuses and both output channels are useful
data. It does not parse diagnostics or decide that an engine result is clean.
An injected ``ProcessRunner`` override is not necessarily a
``ResultProcessRunner``; check the capability before using it.

.. code-block:: php

   $paths = $process->capture(['git', 'ls-files', '-z'], $directory);
   $status = $process->run([PHP_BINARY, '-n', '-l', $file], $directory);

   if (!$process instanceof \FastForward\CodeStandards\Process\ResultProcessRunner) {
       throw new \RuntimeException('This transport does not support result capture.');
   }

   $result = $process->captureResult([PHP_BINARY, '-n', '-l', $file], $directory);
   // Interpret $result->exitCode, stdout and stderr according to the selected tool.

These examples assume an explicitly supplied transport, existing working
directory and selected source file. They do not construct a container or execute
an application bootstrap. The result-capture capability is a transport foundation;
it does not itself provide structured ECS/Rector reports or a CLI JSON option.

Shared execution boundary
--------------------------

The executable and arguments are passed as a literal vector. The interface does
not interpolate a shell command. A caller can deliberately select a shell or
interpreter as the executable; its own command language remains the caller's
responsibility. The directory identifies the requested child working directory;
the native transport sets it without changing the parent's working directory.

Callers supply a positive finite timeout to all three operations. The native
transport rejects invalid bounds before launching the child; the interface's
input precondition does not prescribe every override's validation algorithm.
The defaults are 120 seconds for ``capture`` and 300 seconds for ``run`` and
``captureResult``. The native implementation raises
``InvalidArgumentException`` for invalid bounds and Symfony Process runtime
exceptions when execution cannot complete, including timeout failures.
``capture`` also raises a process failure for a completed nonzero exit; ``run``
and ``captureResult`` retain such statuses. A missing executable can itself
produce a native failure status; do not infer a particular exception from every
unsuccessful command.

Capturing output is not a read-only guarantee. Any selected command or trusted
configuration may write files, execute code or contact external services. Callers
own that authority and the tool-specific interpretation of status and output.
The native transport removes ``GH_TOKEN``, ``GITHUB_TOKEN`` and ``COMPOSER_AUTH``
from child environment inheritance. It does not promise an empty environment,
filesystem isolation, output redaction or rollback of child side effects.

Preservation and extension
--------------------------

The default formatter is configured to retain descriptive interface PHPDoc,
normative clauses and inheritance markers. Golden fixtures exercise read-only
checking, explicit fixing and a stable second pass. A separately qualified native Rector rule also
preserves these contracts while transforming implementation code. Arbitrary
consumer-selected Rector rules can change PHPDoc; this is not a guarantee about
every semantic configuration.

Custom transport overrides remain responsible for the documented execution and
failure semantics. They MAY implement only ``ProcessRunner``; the
``ResultProcessRunner`` capability is optional and callers discover it explicitly.
