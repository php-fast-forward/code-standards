Optional magic-number analysis
==============================

``magic-numbers`` runs PHPMND independently of syntax/style checking. Neither
``check`` nor ``fix`` invokes it. The analyzer reads the selected source; it
does not replace literals, load ECS configuration, install dependencies or
update Composer locks. PHP DOM is optional for the package and required only
for the native XML reporter; text analysis and style checking do not require it.

Usage and selection
-------------------

.. code-block:: sh

   vendor/bin/code-standards magic-numbers --all
   vendor/bin/code-standards magic-numbers --all --format=xml > magic-numbers.xml
   vendor/bin/code-standards magic-numbers --base=<base-sha> --head=<head-sha> --format=xml

Use exactly ``--all`` or both immutable 40-character ``--base``/``--head`` SHAs.
``--directory`` (``-C``) selects the Git checkout root. Changed mode requires the
requested HEAD and a clean tracked checkout. Selection uses the PR merge base,
ignores deleted paths and respects the consumer's root/nested ``.gitignore``
rules, including force-added tracked files matching those rules. Only tracked
PHP files and PHP scripts under ``bin/`` are eligible; escaping/symlink paths
are rejected. Paths are sorted and passed as separate absolute arguments,
including names containing spaces, newlines or UTF-8.

PHPMND runs from its own installed package directory. Its implicit
``cwd/vendor/autoload.php`` discovery therefore does not load the consumer's
autoload file. The tool's own Composer bootstrap remains intentional. No
consumer PHP configuration or optional application bootstrap is accepted by
this command.

Native policy overrides
-----------------------

The published PHPMND profile checks condition, return and switch-case contexts
by default, ignoring numeric values ``0`` and ``1``. The supported options map
directly to native PHPMND options, without an additional configuration language:

* ``--ignore-numbers=42`` adds numeric exceptions to PHPMND's fixed zero/one
  exceptions. An explicit empty value still preserves those native exceptions;
  it does not enable checking of zero or one. Non-numeric or infinite values
  (such as an overflowing exponent) fail before analysis.
* ``--extensions=argument`` adds a native analysis context to the defaults.
  ``--extensions=all,-argument`` selects all native contexts except argument.
  These are AST contexts, **not** filename extensions. PHPMND validates names.
* ``--ignore-funcs=output,fixture`` provides native function exceptions when the
  argument context is enabled. It does not enable that context itself.

.. code-block:: sh

   vendor/bin/code-standards magic-numbers --all --extensions=all,-argument
   vendor/bin/code-standards magic-numbers --all --extensions=argument --ignore-funcs=output

Output and status
-----------------

``--format=text`` is the native human-readable report. Its resource/timing
footer is not byte deterministic. ``--format=xml`` streams the native XML
document to stdout, without ANSI, progress, banners or timing. Diagnostics use
stderr. For the same selected paths, contents, options and installed PHPMND
version, XML is byte deterministic; file paths are absolute and therefore
depend on the checkout location. XML attributes preserve unusual path bytes
through normal XML escaping.

If PHP DOM is unavailable, XML mode fails with status ``2`` and a stderr
diagnostic before checking source encoding, executing PHPMND or writing stdout,
including for an empty selection. Enable DOM or choose text output explicitly.

XML mode requires every selected path and the complete contents of every
selected source to be valid UTF-8 containing only XML 1.0 characters. It rejects
unsupported control characters or invalid encoding before starting the analyzer
or writing stdout, even when the affected file would produce no findings.
No bytes are sanitized or silently omitted. Tabs, newlines, UTF-8 and embedded
CDATA terminators are preserved through the native DOM serializer. Text mode
keeps the native analyzer's encoding behavior without this XML-specific check.

When selection is empty, the engine is not executed. The command emits the
native empty-result XML structure with the installed version, or a text
selection message. It does not report that any file was analyzed.

Exit ``0`` means no findings; exit ``1`` preserves native findings/analyzer
failure status. Invalid command settings, Git scope or process errors fail
with status ``2``. Failure is never converted to a clean result. Redirected XML
is an artifact explicitly requested by the caller; source and lock files are
unchanged.

One invocation is limited to 64 KiB of argument bytes, including options and
paths. An oversized selection fails before execution, without silently dropping
files or returning partial XML. Changed-file selection can reduce scope but
does not guarantee it fits; large full scans need a separate batching increment.
Concatenating multiple XML documents is not supported as one report.

Adoption and baseline guidance
------------------------------

Start with an explicit local invocation and inspect the native XML artifact.
Keep the command outside mandatory checks until the team agrees on useful
contexts and exceptions. Prefer named constants when they clarify intent;
numeric-heavy domains may need carefully reviewed native exceptions.

Numeric/function/context exceptions apply broadly; they are **not** a
per-finding baseline. PHPMND's native whitelist selects filenames and is not
a finding baseline either. This command does not expose that newline-based
transport, automatically accept existing debt, generate suppression files or
claim baseline enforcement. Teams needing precise accepted-debt comparison
must retain the report and review new findings explicitly until such a contract
is implemented. No repository-wide rollout is performed by enabling the tool.

References
----------

* `PHPMND published source and CLI <https://github.com/povils/phpmnd/tree/v3.6.1>`_
* `Optional profile issue <https://github.com/php-fast-forward/code-standards/issues/8>`_
