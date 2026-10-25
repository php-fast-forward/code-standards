# Source PHPDoc: legacy rules and extraction boundary

Fixed DevTools source `35b5f78ac63813ec07aa3fb3eda08751541e28e1`; source inventory
for extraction planning. These observations do not establish runtime parity.
Potential edge cases below are future fixture requirements, not reproduced bugs
or an adopted rule profile.

## Registration matters

There are three custom classes, but they are not all enabled by the inspected
default. `Config/RectorConfig::DEFAULT_RULES` registers
AddMissingMethodPhpDocRector and RemoveEmptyDocBlockRector, plus external Faker
GeneratorPropertyFetchToMethodCallRector. AddMissingClassPhpDocRector exists but
is absent from that default list. Do not equate class presence with activation.

The config also enables broad DEAD_CODE/CODE_QUALITY/CODING_STYLE/
TYPE_DECLARATION/PRIVATIZATION/INSTANCEOF/EARLY_RETURN sets, derives PHP migration
sets from consumer Composer, enables name import/removal, and conditionally loads
Safe migrations. These effects must not come along implicitly with a PHPDoc
extraction. Customization runs last in the native Rector callback.

## Observed class contracts

| Class | Inspected behavior | Required future goldens before selecting policy |
| --- | --- | --- |
| AddMissingClassPhpDocRector | Targets Class_ only, skips any existing Doc; generates class-name summary and namespace @package when available | Named/anonymous/namespaced classes; preserve useful existing prose/tags; choose whether generated boilerplate adds useful human information |
| AddMissingMethodPhpDocRector | Targets ClassMethod; skips the entire method when any Doc exists; creates @param from native types or mixed, @return except constructors, and @throws from literal new named exceptions found recursively | Existing partial docs vs absent docs, typed/untyped/nullable/union/intersection/DNF, variadic/reference parameters, constructors, abstract methods, nested closures and exception-name imports |
| RemoveEmptyDocBlockRector | Targets Class_/ClassMethod only; removes whitespace/star-only Doc; preserves other comment objects and clears Rector/AST doc attributes | Empty vs meaningful prose/tags, other comments, declaration docs on interfaces/traits/enums and docs on properties/functions outside this target set, methods inside interfaces/traits/enums still targeted as ClassMethod, repeated passes and native Rector cache/API compatibility |

These observations derive from the exact source, not generated example output.
In particular, the method rule does not fill missing tags in an existing partial
docblock, despite a broad reading of its description. Recursive throw traversal
and type rendering require dedicated fixture decisions; no unsupported claim of
precise escaping-exception inference or all-syntax correctness is made here.

## Extraction boundary

The initial Code Standards preset preserves existing PHPDoc and does not promise
useful documentation generation by restating syntax. Select human-useful policy
before copying any algorithm. Keep any later semantic operation explicit,
opt-in and target-PHP aware, separate from default check/fix. Reuse native APIs
and focused services rather than importing legacy broad configuration or a DSL.

Future comparative goldens must isolate each selected rule first, then test the
selected combined ordering against ECS. Assert source-readonly check, explicit
write/second-pass idempotence, exact exits, comments/attribute spacing and config
override precedence. Do not execute automatic Safe/Faker/PHP migrations as part
of that PHPDoc comparison. Detailed engine compatibility and behavior remain
unverified; this inventory does not close issue5 or issue4.

Immutable source links:
- https://github.com/php-fast-forward/dev-tools/blob/35b5f78ac63813ec07aa3fb3eda08751541e28e1/src/Config/RectorConfig.php
- https://github.com/php-fast-forward/dev-tools/blob/35b5f78ac63813ec07aa3fb3eda08751541e28e1/src/Rector/AddMissingClassPhpDocRector.php
- https://github.com/php-fast-forward/dev-tools/blob/35b5f78ac63813ec07aa3fb3eda08751541e28e1/src/Rector/AddMissingMethodPhpDocRector.php
- https://github.com/php-fast-forward/dev-tools/blob/35b5f78ac63813ec07aa3fb3eda08751541e28e1/src/Rector/RemoveEmptyDocBlockRector.php
