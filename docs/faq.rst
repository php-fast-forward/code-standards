Frequently asked questions
==========================

Does check change my source?
   No. It can use a temporary ECS cache, but does not pass --fix, update the lock
   or install dependencies.

Does fix commit or push changes?
   No. It applies local formatting only; inspect partial changes after errors.

Does --all include untracked files?
   No. It selects tracked files from Git and permits local tracked edits.

Can I run without Git?
   Formatting check/fix require a Git checkout root. Direct native ECS consumption
   and the explicit native Rector configuration have their own source paths;
   semantic refactoring does not borrow the formatter's Git selection.

Can I use it as a staged pre-commit check?
   The CLI analyzes the working tree, not index blobs. Changed mode requires a
   clean tracked checkout, so it cannot guarantee staged-only content.

Why did changed mode reject HEAD?
   Its exact immutable head must match the checked-out commit. Fetch the needed
   history and inspect your checkout rather than weakening the check.

Why was the whole checkout checked?
   Composer/ECS/EditorConfig and root or nested ``.gitignore`` changes can change
   effective rules or eligibility and trigger a full eligible tracked scan,
   including ignore-policy removal and rename.

Is PHPStan or Rector included?
   The formatter runs neither. The separate opt-in ``refactor`` command uses
   native Rector with explicit configuration and target PHP; see the
   `refactoring guide <rector.rst>`__. PHPStan is its engine dependency, not
   an exposed type-analysis command. PHP syntax validation does not prove type
   correctness or semantic safety.

How can I customize a rule?
   Pass a native builder callback to ECSConfig::configure(), save the returned
   builder in a PHP config file and select it with --config.

Can I assume v0 and 0.1.0 exist?
   No. Verify actual releases and channel refs independently before adopting
   Composer constraints or copying a workflow wrapper.

Does CPX isolate all library namespaces?
   No. Separate installation does not isolate classes loaded in one PHP process.

How is the application composed?
   AsCommand POPOs are registered through Symfony Console, and Fast Forward
   Container autowires concrete services with explicit provider bindings.
