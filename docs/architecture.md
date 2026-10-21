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

CPX separates installation and dependency resolution. It does not isolate PHP
namespaces when PHPUnit, Rector or executable configuration loads the consumer
and tool in the same process. Version conflicts and global state still require
explicit compatibility tests or a real process boundary. A separate Composer
graph alone must never be described as runtime isolation.

## Bootstrap and compatibility

The first Code Standards increment uses PHPUnit directly and deterministic local
checks, without waiting for a future Testing tool or other extracted libraries.
Its subprocess transport uses an existing library. Its actual ECS check/fix and
syntax checks run as bounded child processes; `php -n -l` parses source without
executing it. ECS configuration is executable PHP inside that tool's process.

Own source checks can use the source checkout during bootstrap. Cross-tool
consumption should select compatible published versions once those versions
exist; no dependency or example may point to an invented release. Workflow and
runtime commits must match. Distinguish tool runtime requirements, Composer
resolution and the consumer application's supported PHP language/API minimum.
No automatic PHP migrations derive authority from the host tool's PHP version.

## Consequences and next gates

This small slice validates syntax and PSR-12 style. Type analysis and semantic
refactoring remain separate scoped issues with explicit opt-in and compatibility
evidence. Changelog owns version PR/tag/release transactions; Code Standards does
not duplicate its engine. Application tests, coverage and full reports remain
outside this domain. No consumer migration follows implicitly from extraction.

Backlog transfers require a confirmed destination issue with context and links
before commenting/closing the DevTools original. Mixed issues retain their
DevTools scope. Await the coordinated inventory before creating migrated
duplicates; current DevTools bug PRs stay in that repository.
