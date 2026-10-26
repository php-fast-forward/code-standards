# Explicit semantic refactoring

This opt-in increment targets a future minor release. It does not change the
0.1 syntax/style contract, select a migration profile, or authorize publication.
`check` and `fix` never invoke the semantic runner. The separate invocable
`refactor` command uses the existing container factories and process transport.

The qualified lock selects published Rector 2.4.2, reference
`e645b6463c6a88ea5b44b17d3387d35a912c7946`, with PHPStan 2.1.51. The manifest
restricts both dependencies to their respective 2.4/2.1 patch lines. Rector's
upstream PHPStan constraint also permits 2.3.2, but that combination reproduced
a fatal missing `RichParser::$container` error before rule execution. This
compatibility restriction applies to the shared Composer graph; a consumer
requiring a newer PHPStan must use a separate compatible tool installation.
PHPStan is an engine dependency here, not a newly exposed analysis command.
Dependency updates require the real integration fixtures below. Native configuration is the only
extension point; no PHP parser, configuration translator or engine framework is
introduced. Configuration paths resolve from `--directory` or the current
directory. This command deliberately uses the configuration's whole-project
paths rather than borrowing the formatter's changed-file list.

```php
<?php

use Rector\Config\RectorConfig;
use Rector\Php54\Rector\Array_\LongArrayToShortArrayRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPhpVersion(PhpVersion::PHP_74)
    ->withPaths([__DIR__ . '/src'])
    ->withRules([LongArrayToShortArrayRector::class])
    ->withCache(sys_get_temp_dir() . '/my-project-rector/' . hash('sha256', __DIR__))
    ->withoutParallel();
```

The target is a consumer decision, independent of the host PHP. `withPhpVersion`
limits supported transformations; it does not downgrade existing code or select
PHP migration sets. Review any later `withPhpSets` selection separately. The
command does not infer or overwrite target, rule, cache, bootstrap, parallelism
or path choices. Configure generated caches separately from owned source, with
a directory unique to the checkout. No consumer dependency/lock update occurs.

```sh
php bin/code-standards refactor --config=rector.php --directory=/project
# After reviewing the proposed semantic diff:
php bin/code-standards refactor --config=rector.php --directory=/project --apply
```

Preview passes native `--dry-run`. Explicit apply performs one write pass and
then a dry-run verification; remaining differences fail. Native exits are 0 for
clean/success, 1 for processing errors and 2 for proposed changes. Controlled
invocation, empty-rule and invalid-inventory errors also return 2, with an error
diagnostic. Other native fatal exits propagate unchanged. Rector 2.4.2 uses `-n` for dry-run and does not expose the ECS
`--no-interaction` option; its native flags are not translated blindly.

## Executable configuration and initialization boundary

The command first asks native `list-rules --output-format=json` for the selected
configuration's rules. An empty or invalid inventory is rejected without calling
`process`: native Rector otherwise can generate `rector.php` and return success
even during dry-run. Missing explicit configuration is rejected before launch.

This is a contract for **trusted, deterministic configuration**. Preview loads
it twice; successful apply and verification load it three times. A configuration
that changes its rules between invocations can defeat that preflight. Arbitrary
configuration, Composer autoload files, custom rules and configured bootstraps
can execute and mutate files even in dry-run. The command is not a filesystem or
security sandbox, and cannot promise read-only behavior for such executable
extensions. Do not use them with privileged events, secrets or an untrusted
consumer. Changes to the native initialization contract require regression
review before changing the pinned Rector version.

The subprocess prevents Rector from sharing the parent CLI's in-memory state.
It does not guarantee namespace isolation in the child: Rector loads its
installation autoloader and may load the consumer's `vendor/autoload.php` too.
The collision regression requires failure rather than a false clean result;
configuration/bootstrap compatibility remains a consumer qualification gate.
Captured preflight and streamed processing use the same credential stripping.
No generic promise of semantic safety follows from a zero result.

The integration fixtures cover no-write preview, explicit apply/verification, empty custom
configuration, a PHP 7.4 target under a newer host, configured cache ownership,
native bootstrap failures and consumer/tool autoload collisions. Run the
consumer's affected behavioral tests and review API/side-effect changes before
accepting any semantic patch. Type analysis and application testing are not
implicitly added to this command.
