# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.1] - 2026-10-26

### Fixed

- Uses the published Changelog 0.4.1 lifecycle's native repository and version title
  template when preparing or refreshing the managed version pull request, without
  overriding the generated title in this repository.

  ([#50](https://github.com/php-fast-forward/code-standards/pull/50))

## [0.2.0] - 2026-10-26

### Changed

- Organize the README around verified installation, commands, configuration, integration and compatibility, with source-backed badges, API summary and contributor guidance. ([#34](https://github.com/php-fast-forward/code-standards/pull/34))
- Use ECS 13.3's native non-risky PER Coding Style 3.0 set in place of PSR-12, preserving explicit command-attribute spacing and callback-last overrides. Keep destructive PHPDoc cleanup rules skipped so descriptions, inheritance markers, analysis types and intentional annotations survive formatting. Remove inactive conversion/global-import skips without implicitly enabling those rules. ([#40](https://github.com/php-fast-forward/code-standards/pull/40))

## [0.1.0] - 2026-10-26

### Added

- Introduces standalone non-risky ECS style configuration, explicit check/fix CLI,
  PHP syntax validation and exact-head changed-file checking with a portable
  consumer skill and reusable workflow examples.

  ([#7](https://github.com/php-fast-forward/code-standards/pull/7))
- Complete package metadata and portable skill discovery, declare direct container
  and Composer binary-runtime contracts, and validate metadata/platform in self-checks.
  Keep formatter data fixtures outside development classmaps during optimized installs.

  ([#25](https://github.com/php-fast-forward/code-standards/pull/25))
- Adds the released-only v0 workflow channel, exact executing-workflow runtime
  provenance and guarded conditional channel updates after verified 0.x publication.

  ([#13](https://github.com/php-fast-forward/code-standards/pull/13))

### Changed

- Composes the attributed invocable commands through the published Fast Forward
  Container autowiring API, with focused provider bindings and explicit overrides.

  ([#16](https://github.com/php-fast-forward/code-standards/pull/16))
- Record resolved legacy fixer rules, options and loaded-source provenance without broadening the initial default policy. ([#17](https://github.com/php-fast-forward/code-standards/pull/17))
- Verifies the compiled non-risky ECS preset and representative golden style,
  PHPDoc preservation and explicit fix idempotence contracts.

  ([#15](https://github.com/php-fast-forward/code-standards/pull/15))
- Place the tool's StandardsServiceProvider and composition tests in the
  ServiceProvider namespace, matching the provider's responsibility before the
  first publication. Third-party dependency-injection APIs remain unchanged.
  Use the published Container factories for lazy service bindings, preserving
  native autowiring, explicit scalar inputs and consumer overrides.

  ([#28](https://github.com/php-fast-forward/code-standards/pull/28))
- Documents the legacy PHPDoc rule activation and future analyzer boundaries,
  including native whole-project PHPStan scope, without enabling new policies.

  ([#19](https://github.com/php-fast-forward/code-standards/pull/19))

### Fixed

- Isolate the default ECS cache by executing checkout so another project's
  configuration invalidation cannot erase its cache, preserving explicit overrides.

  ([#23](https://github.com/php-fast-forward/code-standards/pull/23))
- Preserve whitespace and control bytes in checkout root names while rejecting nested, metadata and bare repository directories. ([#18](https://github.com/php-fast-forward/code-standards/pull/18))
- Skips non-PHP documentation symlinks before source-path validation while retaining
  symlink rejection for PHP and bin candidates, and pins workflow Actions to reviewed
  immutable commits.

  ([#7](https://github.com/php-fast-forward/code-standards/pull/7))
- Preserve each stacked contribution's verified implementation PR in release notes using explicit fragment metadata, rather than attributing every inherited fragment to the umbrella merge. ([#38](https://github.com/php-fast-forward/code-standards/pull/38))
- Loads the default ECS preset through symlinked Composer path installations
  without requiring a separate vendor directory beside the source checkout.

  ([#7](https://github.com/php-fast-forward/code-standards/pull/7))
- Validate the native executing-job JSON protocol before emitting immutable checkout
  identity, rejecting missing, ambiguous or malformed values without custom validators.

  ([#13](https://github.com/php-fast-forward/code-standards/pull/13))
- Verify each explicit formatter batch with a read-only check and propagate residual
  violations rather than reporting successful completion of a partially normalized file.

  ([#26](https://github.com/php-fast-forward/code-standards/pull/26))

[0.2.1]: https://github.com/php-fast-forward/code-standards/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/php-fast-forward/code-standards/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/php-fast-forward/code-standards/releases/tag/v0.1.0
