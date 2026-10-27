Optional workflow analyses and summaries
========================================

The public reusable keeps the mandatory style/syntax check unchanged. A separate
hosted job reports optional analysis without making ``check`` or ``fix`` invoke
semantic or quality tools. Both analyzers are **disabled by default**. With no
optional tool enabled, that job writes disabled statuses without checking out,
installing or executing another tool graph.

Inputs and adoption
-------------------

The existing ``php-version`` and ``config`` inputs retain their contracts.
``config`` belongs to the formatter; it does not enable or configure Rector.
The additional inputs are:

* ``magic-numbers``: boolean, default ``false``; analyze the exact eligible PHP
  selection through the separate ``magic-numbers`` command.
* ``magic-numbers-policy``: ``report`` (default) or ``fail`` for verified findings.
* ``rector``: boolean, default ``false``; run native semantic preview only.
* ``rector-config``: required when Rector is enabled; an existing consumer-relative
  PHP configuration resolving inside the checkout, with explicit target/rules.
  Native configuration owns Rector's whole-project paths; these are not restricted
  to the formatter changed-file list. No automatic ``--apply`` is possible.
* ``rector-policy``: ``report`` (default) or ``fail`` for verified proposed changes.

Policies are exact strings; unsupported values fail explicitly. A policy only
changes the treatment of **verified findings**. Operational errors always block,
including when the selected policy is ``report``. Tools are not enabled because
their dependency is installed or a configuration filename happens to exist.

The consumer wrapper in ``resources/workflows/code-standards.yml`` provides the
basic PR style check through the published ``v0`` channel. The fragment below
describes the optional-analysis inputs. The selected workflow revision defines
its supported input contract; callers supply inputs belonging to that revision.
GitHub workflow channels and Composer/CPX package constraints are separate
distribution choices. The unprivileged PR event and runtime/ref provenance
contract apply to both basic checks and explicitly enabled analyses.

.. code-block:: yaml

   with:
     php-version: '8.3'
     magic-numbers: true
     magic-numbers-policy: report
     rector: true
     rector-config: rector.php
     rector-policy: fail

Result states and counts
------------------------

The job summary distinguishes:

* ``disabled``: not requested; no optional analyzer execution occurred.
* ``not-executed``: valid Magic Numbers selection contains no eligible PHP files;
  no engine execution or clean native result is claimed.
* ``clean``: an executed, complete native report and exit agree on zero findings.
* ``findings``: complete native findings/differences; report-only policy informs,
  while fail policy blocks.
* ``operational-error``: failed prerequisites, execution/timeout, malformed or
  inconsistent output, missing capability or exceeded limits. Missing results
  are not silently replaced with zero counts.

For Magic Numbers, ``selected PHP files`` comes from the existing
``FileSelection``/``PhpFiles`` contract, not XML ``fileCount``. Native ``fileCount``
counts only files containing findings. ``Findings`` counts native entries; their
real file/line values are shown. XML columns are not safe AST-patch identities.
An empty eligible selection is checked before launching the analyzer.
Selection runs only when Magic Numbers is enabled; its failure does not prevent
Rector from producing its own result using the configured whole-project paths.

For Rector, ``changed files`` is the native total, not a count of individual
rules, lines or defects. Available native error locations retain their line
field. Proposed changes have no native JSON line field; the summary marks that
location unavailable rather than parsing a unified diff or inventing precision.
The explicit CLI JSON representation retains the native diff for reviewed use.

Classification requires the complete structured report **and** the process exit.
PHPMND exit 1 alone can mean findings or failure. Rector exit 2 alone can mean
proposed changes or a controlled invocation error. Unsupported combinations fail
closed. A mixed Rector report containing errors remains operational failure even
when it also includes proposed changes.

Execution, summary and trust limits
-----------------------------------

The workflow uses only ``pull_request``, ``contents: read``, GitHub-hosted Ubuntu,
the called job's verified repository/SHA, exact consumer head, separate checkouts
and no persisted checkout credentials. Only the tool's lock is installed, with
plugins/scripts disabled; consumer dependencies are not installed. Configured
optional tools do not change protections, required checks, releases or consumers.

Each optional invocation has a total 300-second budget, a 2 MiB stdout report cap
and a separate 64 KiB diagnostic cap. The total Rector budget includes its native
inventory and processing, whereas the standalone CLI timeout is per subprocess.
The existing Magic Numbers argv bound is preserved. Overflow discards classification
of the incomplete report and reports operational error with unavailable counts;
it is not a truncated clean result.

The workflow starts the Code Standards PHP CLI with ``memory_limit=256M``.
This constrains that PHP process, including its buffered native Rector preflight
output. It is not a global process-tree or native Rector memory limit: PHP
subprocesses have their own configuration, and executable configuration may alter
its own INI settings. No persistent INI file or standalone CLI default is changed.

The workflow-private Linux executor starts a trusted launcher in its own
``setsid`` session, checks the child's PID/session/process-group identity and only
then permits the payload. It reads fixed nonblocking chunks with deadline checks;
it does not depend on a Symfony callback after unbounded intermediate pipe reads.
Only that confirmed owned group is terminated on completion/failure, including
ordinary analyzer descendants. Missing ``setsid`` or PHP POSIX support fails before
launch instead of choosing a weaker fallback. Invocation temporary files belong
to an owned temporary directory; cleanup does not follow symlinks. These are
workflow requirements, not new global Composer/CLI platform requirements.
The private child environment omits the existing tool credential keys plus
GitHub command-file paths and known Actions token fields. CI/workspace metadata
remain available. This narrows ambient capabilities; it does not claim that
arbitrary PHP is sandboxed from the runner filesystem.

Reports are parsed without network/external-entity resolution and XML DTDs are
rejected. Root/files/file structural boundaries allow only the expected elements,
whitespace text and comments; other nodes, including CDATA and processing
instructions, reject classification. CDATA inside native source snippets remains
payload. The summary displays bounded, escaped preformatted text; source snippets,
full diffs and untrusted HTML/Markdown are not interpreted. Control/non-ASCII
characters use visible JSON escapes. Display is limited to 100 diagnostics per
tool and 64 KiB overall with explicit omission/truncation notices; validated
native totals are not reduced to the displayed subset. Raw reports are not
uploaded as artifacts or shared automatically.

The classification step writes its summary and a controlled blocking decision.
A mandatory final gate fails for operational errors, configured blocking findings,
or incomplete/missing outputs; there is no blanket ``continue-on-error``.
An ``always()`` fallback writes unavailable/error statuses if setup or the helper
fails before it can provide a complete result. Platform cancellation or outages
can prevent cleanup/final steps and never establish a successful analysis.

Rector configuration, custom rules and bootstraps are executable PHP and can
mutate files even during preview. This is not a security/filesystem sandbox or
a guarantee against malicious code which detaches another session. The boundary
is the unprivileged ephemeral hosted job and finite runtime. Never call it from
a privileged target event or supply secrets. Review the
:doc:`native refactoring contract <rector>` before enabling it.
