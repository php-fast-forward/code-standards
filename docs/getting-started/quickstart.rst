First check and explicit fix
============================

Run these commands at the Git checkout root after installation:

.. code-block:: console

   php bin/code-standards check --all
   php bin/code-standards fix --all
   php bin/code-standards check --all

``check`` reports syntax/style violations without changing source. ``fix``
explicitly allows ECS to edit selected sources. It does not commit, push, update
locks or install dependencies. Inspect its diff even on failure: a formatter
batch can have partially changed files before another batch fails.

A successful formatter batch is followed by a read-only verification. Some
interacting rules need another explicit fix pass; remaining violations are
reported as failure, not hidden behind a successful formatter exit.

.. code-block:: console

   php bin/code-standards help check
   php bin/code-standards check --all --directory=/absolute/project/path

The selected directory must be a Git checkout root. Nested directories, bare
repositories and projects without Git are rejected with an objective error.
