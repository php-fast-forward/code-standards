# Architecture decision: libraries, tools and bootstrap

Status: accepted direction for the initial Code Standards extraction.

## Context

DevTools combines independently reusable libraries and developer tools. Extracting
that combination must not introduce a dependency cycle or require all tools to
exist before the first tool can be tested. See
[DevTools #379](https://github.com/php-fast-forward/dev-tools/issues/379) and the
[initial backlog](https://github.com/php-fast-forward/code-standards/issues).

## Decision

Libraries expose reusable runtime behavior: Reflection, Console, Iterators and
Enum are examples of library responsibilities. Tools own executable development
workflows: Changelog, Code Standards and Testing are tool responsibilities.
These are dependency roles, not a request to create Reflection or Console now.

Tools may depend on libraries at runtime. Libraries may consume tools as
development dependencies. A library must not require a development tool for its
normal runtime behavior. Tools must not depend on DevTools in production or
create a circular tool/bootstrap prerequisite. Code Standards provides its
focused preset as a library API for tool consumers while remaining a development
tool; that API does not make application tests/reports part of its domain.
`require-dev` dependencies are not transitively installed for library consumers:
an application explicitly selects the development tools it actually uses.

CPX separates installation and dependency resolution. It does not isolate PHP
namespaces when PHPUnit, Rector or executable configuration loads the consumer
and tool in the same process. Version conflicts and global state still require
explicit compatibility tests or a real process boundary. A separate Composer
graph alone must never be described as runtime isolation.
Analyzer/autoload fixtures must prove that the code under analysis comes from
the consumer checkout, rather than another copy installed inside the tool.
This initial syntax parser executes no application autoload; future PHPUnit,
Rector and type-analysis adapters must add those compatibility tests.

## Bootstrap and compatibility

The first Code Standards increment uses PHPUnit directly and deterministic local
checks, without waiting for a future Testing tool or other extracted libraries.
Its subprocess transport uses an existing library. Its actual ECS check/fix and
syntax checks run as bounded child processes; `php -n -l` parses source without
executing it. ECS configuration is executable PHP inside that tool's process.

Own source checks can use the source checkout during bootstrap. Once published,
self-consumption selects a compatible previous published tool version rather
than depending on the version currently being built. Cross-tool
consumption should select compatible published versions once those versions
exist; no dependency or example may point to an invented release. Workflow and
runtime commits must match. Distinguish tool runtime requirements, Composer
resolution and the consumer application's supported PHP language/API minimum.
No automatic PHP migrations derive authority from the host tool's PHP version.

## CLI composition and configuration contract

Changelog's console separates commands, input definitions and injected services.
Code Standards follows that separation with Symfony Console 7.4-compatible APIs,
matching this project's PHP 8.3 minimum. Symfony owns argument binding, help and
global options. Two inexpensive commands are composed directly in the binary;
there is no custom parser, service container or speculative lazy-command layer.
Project-scope validation, Git selection, PHP filtering and tool execution have
separate responsibilities. The process transport receives its output sink;
reusable services do not write to global standard-output handles.

The supported configuration extension is the native ECS builder. A small PSR-4
preset class returns that builder and applies an optional callback last. An array
would require a second schema/translator; a global helper function would add an
autoload convention without simplifying native ECS consumption. Neither is
needed. Internal CLI classes are implementation details, not a promise of API
compatibility with DevTools. No custom DSL or automatic configuration discovery
is introduced.

No configuration is needed for the default preset. A consumer selects an existing
readable PHP file with `--config`; relative paths resolve from the project root.
That file must return the configuration supported by the installed ECS version.
Native builder methods define rule imports, skips and overrides. Invalid paths,
unsupported options and conflicting scan modes fail with an objective error;
empty paths are rejected rather than coerced to the current directory. ECS
configuration errors propagate as failures. Standard Symfony flag semantics
apply, including idempotent repeated boolean flags and both `--option=value`
and `--option value`. Behavioral tests exercise the default preset, real rule
overrides, help outside a checkout and invalid invocations.

## Implementation quality

Keep methods focused on one responsibility and use early returns for rejected
inputs. Inject collaborators and execution/output boundaries explicitly. Reuse
established library mechanisms when they solve an actual need; introduce no
framework, duplicate configuration schema or abstraction for hypothetical work.
Separate logical blocks with whitespace and add PHPDoc that explains contracts,
failure conditions and meaningful collection/callback types. Avoid comments
that merely repeat syntax. Regression tests assert observable behavior, errors,
exit statuses and source preservation rather than private implementation shape.

## Consequences and next gates

This small slice validates syntax and PSR-12 style. Type analysis and semantic
refactoring remain separate scoped issues with explicit opt-in and compatibility
evidence. Changelog owns version PR/tag/release transactions; Code Standards does
not duplicate its engine. Application tests, coverage and full reports remain
outside this domain. No consumer migration follows implicitly from extraction.

## Coordinated backlog boundaries

Code Standards #1 is the destination epic for DevTools #379. Optional magic-value
and Twig evaluations move to #8/#9 and do not block 0.1.0. Relevant skill
procedures from DevTools #195 and ECS/Rector structured-result criteria from
#248 belong here, while both mixed source issues remain open. LLM Skills owns
discovery/install/update; this tool owns its canonical skill payload and does
not introduce another installer or sync system.

DevTools #14/#265/#266/#267 remain conditional analysis candidates. Ownership
between a Code Standards module and another analysis domain remains undecided;
Code Standards #3 is a bounded investigation, not an implicit transfer or engine
choice. Validate #267's actual engine before treating its proposed PHPCS/PHPStan
relationship as fact. None is a prerequisite for initial 0.1.0.

Doctor #142, distribution #197, published refs #238, orchestration #240/#384 and
Actions governance #347–349 remain with DevTools. Current bugs and correction
PRs remain there. Historical extensibility/options/config/path contracts are
criteria, not automatically reopened tasks.

Backlog transfers require a confirmed destination issue with context and links
before commenting/closing the DevTools original. Mixed issues retain their
DevTools scope. Use the coordinated inventory and existing destinations to avoid
duplicates; current DevTools bug PRs stay in that repository.
