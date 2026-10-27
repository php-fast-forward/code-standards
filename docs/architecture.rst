Architecture decision: libraries, tools and bootstrap
=====================================================

Status: accepted direction for the initial Code Standards extraction.

Context
-------

DevTools combines independently reusable libraries and developer tools. Extracting that combination must not introduce a dependency cycle or require all tools to exist before the first tool can be tested. See `DevTools #379 <https://github.com/php-fast-forward/dev-tools/issues/379>`__ and the `initial backlog <https://github.com/php-fast-forward/code-standards/issues>`__.

Decision
--------

Libraries expose reusable runtime behavior: Reflection, Console, Iterators and Enum are examples of library responsibilities. Tools own executable development workflows: Changelog, Code Standards and Testing are tool responsibilities. These are dependency roles, not a request to create Reflection or Console now.

Tools may depend on libraries at runtime. Libraries may consume tools as development dependencies. A library must not require a development tool for its normal runtime behavior. Tools must not depend on DevTools in production or create a circular tool/bootstrap prerequisite. Code Standards provides its focused preset as a library API for tool consumers while remaining a development tool; that API does not make application tests/reports part of its domain. ``require-dev`` dependencies are not transitively installed for library consumers: an application explicitly selects the development tools it actually uses.

CPX separates installation and dependency resolution. It does not isolate PHP namespaces when PHPUnit, Rector or executable configuration loads the consumer and tool in the same process. Version conflicts and global state still require explicit compatibility tests or a real process boundary. A separate Composer graph alone must never be described as runtime isolation. Analyzer/autoload fixtures must prove that the code under analysis comes from the consumer checkout, rather than another copy installed inside the tool. Syntax parsing uses ``php -n -l`` without application autoload. The formatter worker loads its own Composer runtime and uses ECS's public container factory and native check command. It does not invoke ECS's CLI bootstrap, which otherwise imports the working directory's vendor autoloader before config selection. The worker preserves consumer cwd, native config cache bookkeeping and absolute selected file arguments; executable user config can explicitly opt into consumer loaders and custom checkers. Engine paths are captured from the tool's installation before that config can change Composer's installed-package metadata.

Ordinary Composer installation inside the consumer intentionally shares that installation's autoloader through its binary proxy. ``composer-runtime-api`` and ``$_composer_autoload_path`` remain part of this contract. Removing an extra import from a different target graph does not remove normal Composer bootstrap. PHPUnit, Rector and type-analysis adapters still require their own compatibility tests; the formatter's process boundary does not prove their isolation.

Bootstrap and compatibility
---------------------------

The first Code Standards increment uses PHPUnit directly and deterministic local checks, without waiting for a future Testing tool or other extracted libraries. Its subprocess transport uses an existing library. Its actual ECS check/fix and syntax checks run as bounded child processes; ``php -n -l`` parses source without executing it. ECS configuration is executable PHP inside that tool's process.

Own source checks can use the source checkout during bootstrap. Once published, self-consumption selects a compatible previous published tool version rather than depending on the version currently being built. Cross-tool consumption should select compatible published versions once those versions exist; no dependency or example may point to an invented release. Workflow and runtime commits must match. Distinguish tool runtime requirements, Composer resolution and the consumer application's supported PHP language/API minimum. No automatic PHP migrations derive authority from the host tool's PHP version.

CLI composition and configuration contract
------------------------------------------

Changelog's console separates commands, input definitions and injected services. Code Standards follows that separation with Symfony Console 7.4-compatible APIs, matching this project's PHP 8.3 minimum. Symfony owns argument binding, help and global options. Commands are plain invocable PHP objects with ``AsCommand`` and mapped ``Option`` attributes, adapted by native ``Application::addCommand()``; they do not extend Symfony Command. These APIs are present in published Console 7.4. There is no custom parser or speculative lazy-command layer. Fast Forward Container autowires the concrete application, commands, scan orchestration, file selector and PHP filter. The module's service provider binds only the process/output interfaces and services needing installation/config paths. The binary supplies the actual vendor directory and terminal output; there are no hidden construction defaults or service lookups inside domain services. The container is used only at this composition boundary. The tool-owned provider lives in ``ServiceProvider/StandardsServiceProvider``, matching its responsibility and class suffix. Its composition tests mirror that namespace. Third-party dependency-injection namespaces retain their upstream names. Bindings use the published Container 1.10.1 factories: ``ServiceFactory`` retains the supplied output instance, ``InvokableFactory`` constructs services with explicit installation arguments and service IDs, and ``CallableFactory`` resolves the typed output collaborator for the process adapter. Concrete application services still use autowiring. No unpublished Container behavior or parallel factory layer is needed. Project-scope validation, Git selection, PHP filtering and tool execution have separate responsibilities. The process transport receives its output sink; reusable services do not write to global standard-output handles.

The supported configuration extension is the native ECS builder. A small PSR-4 preset class returns that builder and applies an optional callback last. An array would require a second schema/translator; a global helper function would add an autoload convention without simplifying native ECS consumption. Neither is needed. Internal CLI classes are implementation details, not a promise of API compatibility with DevTools. No custom DSL or automatic configuration discovery is introduced.

No configuration is needed for the default preset. A consumer selects an existing readable PHP file with ``--config``; relative paths resolve from the project root. That file must return the configuration supported by the installed ECS version. Native builder methods define rule imports, skips and overrides. Invalid paths, unsupported options and conflicting scan modes fail with an objective error; empty paths are rejected rather than coerced to the current directory. ECS configuration errors propagate as failures. Standard Symfony flag semantics apply, including idempotent repeated boolean flags and both ``--option=value`` and ``--option value``. Behavioral tests exercise the default preset, real rule overrides, help outside a checkout and invalid invocations.

Implementation quality
----------------------

Keep methods focused on one responsibility and use early returns for rejected inputs. Inject collaborators and execution/output boundaries explicitly. Reuse established library mechanisms when they solve an actual need; introduce no framework, duplicate configuration schema or abstraction for hypothetical work. Separate logical blocks with whitespace and add PHPDoc that explains contracts, failure conditions and meaningful collection/callback types. Avoid comments that merely repeat syntax. Regression tests assert observable behavior, errors, exit statuses and source preservation rather than private implementation shape. Follow Changelog's attribute layout: multiline command metadata, an attribute immediately above its target, and a blank line between parameter groups with attributes and the preceding group. Keep logical blocks visibly separated. The formatter regression verifies both check and fix retain that spacing. The initial preset overrides ``method_argument_space.on_multiline`` to ``ignore``: the upstream ``ensure_fully_multiline`` mode collapses these deliberate blank lines. Standard comma spacing still applies; no custom fixer is introduced.

Published framework composition
-------------------------------

This increment consumes ``fast-forward/container`` 1.10.1 (release commit ``a75dda2c8a9bd9cb2ef5bb25abc1a6b09de8a298``), ``fast-forward/config`` 1.7.3 and ``container-interop/service-provider`` 0.4.1 through the actual Composer graph. These are runtime library dependencies of the tool. Their development tools are not transitively installed. No unpublished branch/PR or future Container PR #49 behavior is required. The provider API is experimental (0.4) and distinct from PSR-11's lookup contract; inspect compatibility when updating dependencies.

Use the published ``ContainerFactory::create()`` for composition. Consumers needing explicit bindings may pass one native ``AggregateServiceProvider`` containing the module provider first and their ``ArrayServiceProvider`` last. Later factories replace earlier factories before services are resolved. Keep replacements typed to the declared interface and apply them before first lookup. Tests exercise lazy construction, shared application identity, nested transport/output overrides, native command binding and exact failure status with the published adapter. No module-specific container, provider registry or override DSL exists.

The default provider defines no extensions. This tool does not claim additional cross-provider decoration or alias guarantees beyond the published adapter. CPX installation isolation does not alter these in-process runtime/autoload boundaries. Command configuration does not double as dependency injection: check/fix are separate invocable entrypoints and transport authority is explicit.

Consequences and next gates
---------------------------

This small slice validates syntax and PER Coding Style 3.0. Type analysis and semantic refactoring remain separate scoped issues with explicit opt-in and compatibility evidence. Changelog owns version PR/tag/release transactions; Code Standards does not duplicate its engine. Application tests, coverage and full reports remain outside this domain. No consumer migration follows implicitly from extraction.

Coordinated backlog boundaries
------------------------------

Code Standards #1 is the destination epic for DevTools #379. Optional magic-value and Twig evaluations move to #8/#9 and do not block 0.1.0. Relevant skill procedures from DevTools #195 and ECS/Rector structured-result criteria from #248 belong here, while both mixed source issues remain open. LLM Skills owns discovery/install/update; this tool owns its canonical skill payload and does not introduce another installer or sync system.

DevTools #14/#265/#266/#267 remain conditional analysis candidates. Ownership between a Code Standards module and another analysis domain remains undecided; Code Standards #3 is a bounded investigation, not an implicit transfer or engine choice. Validate #267's actual engine before treating its proposed PHPCS/PHPStan relationship as fact. None is a prerequisite for initial 0.1.0.

Doctor #142, distribution #197, published refs #238, orchestration #240/#384 and Actions governance #347–349 remain with DevTools. Current bugs and correction PRs remain there. Historical extensibility/options/config/path contracts are criteria, not automatically reopened tasks.

Backlog transfers require a confirmed destination issue with context and links before commenting/closing the DevTools original. Mixed issues retain their DevTools scope. Use the coordinated inventory and existing destinations to avoid duplicates; current DevTools bug PRs stay in that repository.
