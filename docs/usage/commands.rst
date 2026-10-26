Commands and file selection
===========================

Both commands accept ``--all`` or the pair ``--base`` and ``--head``. They also
accept ``--directory`` and ``--config``. Symfony Console supports both
``--option=value`` and ``--option value``. See ``help check`` and ``help fix``
for the installed command definitions.

.. code-block:: console

   php bin/code-standards check --base=BASE_40_CHARACTER_SHA --head=HEAD_40_CHARACTER_SHA

Replace the placeholders with existing immutable commit SHAs. Changed mode
requires HEAD to equal the requested head and a clean tracked checkout. It
selects ``base...head`` destinations using the merge base. Renames, deletions,
spaces and control bytes are handled with Git's NUL-delimited protocol.
Composer/ECS/EditorConfig changes trigger a complete tracked scan.

``--all`` checks tracked PHP files and PHP entrypoints under ``bin/`` and allows
local tracked edits. Untracked files are outside this contract. It is not a
staged-index check. Directory-component exclusions include ``vendor``, ``.git``,
``.build``, ``.dev-tools``, ``backup``, ``cache``, ``public``, ``resources``,
``tmp`` and ``node_modules``. Candidate PHP symlinks fail closed.

Exit statuses
-------------

* Zero means the selected check passed or there were no selected PHP files.
* Invalid input, missing configuration, unavailable history or a mismatched
  checkout fails with status 2 and a diagnostic on stderr.
* Syntax/style subprocess failures retain their nonzero tool status. Executable
  ECS configuration errors are failures too; do not equate every status 2 with
  input validation.

Selection, syntax parsing and ECS execution happen in bounded subprocess
batches. Syntax validation uses ``php -n -l`` without loading application code.
