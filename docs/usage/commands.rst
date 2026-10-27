Commands and file selection
===========================

The formatting commands ``check`` and ``fix`` accept ``--all`` or the pair
``--base`` and ``--head``. They also
accept ``--directory`` and ``--config``. Symfony Console supports both
``--option=value`` and ``--option value``. See ``help check`` and ``help fix``
for the installed command definitions.

``check`` additionally accepts ``--format=text|json`` with text as the default.
The explicit `structured check contract <../structured-check.rst>`__ documents
single-document stdout, native findings, operational failures and partial coverage.
``fix`` retains text output and does not accept this option.

The separate opt-in ``refactor`` command uses an explicit native Rector
configuration's source paths rather than this Git changed-file selection.
See the `semantic refactoring contract <../rector.rst>`__ for required target
PHP, optional native cache configuration, bounded timeouts and execution limits.
Use ``help refactor`` for its installed options.

The opt-in ``magic-numbers`` command uses the same Git selection as ``check`` and
``fix``, independently of formatting. It accepts ``--all`` or ``--base``/``--head``
and ``--directory``; it does not accept or load ECS configuration. Use
``help magic-numbers`` for the installed defaults and native policy options.
See `magic-number analysis <../magic-numbers.rst>`__ for text/XML output,
optional DOM requirements, bounded execution, finding statuses and adoption.

.. code-block:: console

   php bin/code-standards check --base=BASE_40_CHARACTER_SHA --head=HEAD_40_CHARACTER_SHA

Replace the placeholders with existing immutable commit SHAs. Changed mode
requires HEAD to equal the requested head and a clean tracked checkout. It
selects ``base...head`` destinations using the merge base. Renames, deletions,
spaces and control bytes are handled with Git's NUL-delimited protocol.
Composer/ECS/EditorConfig and root or nested ``.gitignore`` changes trigger a
complete eligible tracked scan, including ignore-policy removal and rename.

``--all`` checks tracked PHP files and PHP entrypoints under ``bin/`` and allows
local tracked edits. Untracked files are outside this contract. It is not a
staged-index check. Git dynamically applies the consumer's root and nested
``.gitignore`` rules, including anchors, escapes and negations. Matching tracked
or force-added files are excluded. Global ignores and ``.git/info/exclude`` do
not change this project-owned policy. Only ``.git`` metadata is structurally
excluded, whether it is a directory or a worktree metadata file. Directories
such as ``public``, ``resources`` and ``backup`` are eligible unless the consumer
ignores them. Containment checks and candidate PHP symlink rejection remain in
effect.

Formatting exit statuses
------------------------

* Zero means the selected check passed or there were no selected PHP files.
* Invalid input, missing configuration, unavailable history or a mismatched
  checkout fails with status 2 and a diagnostic on stderr.
* Syntax/style subprocess failures retain their nonzero tool status. Executable
  ECS configuration errors are failures too; do not equate every status 2 with
  input validation.

Formatting selection, syntax parsing and ECS execution happen in bounded subprocess
batches. Syntax validation uses ``php -n -l`` without loading application code.
