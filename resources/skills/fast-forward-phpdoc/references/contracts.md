# Evidence and interface contracts

Start from existing specifications, credible contracts and explicit requirements.
Use implementations, call sites and tests to check conformance, not to promote an
implementation accident into a requirement. Types constrain values; names alone
do not prove streaming, idempotence, atomicity, retries or absence of mutation.
Report conflicts instead of silently weakening an established promise.

For each method, select only relevant and evidenced input meaning, outcomes,
preconditions, effects, failures and invariants. State caller versus implementer
responsibilities where the distinction matters. A replacement implementer must
not require stronger inputs or weaken promised results. Specific storage,
frameworks and algorithms belong on concrete implementations unless the public
specification explicitly requires them.

Keep the summary one short purpose sentence. Place necessary conditions in prose
or tags without repeating them in both. Do not fill a category checklist with
guesses or force normative words into straightforward methods.

## Requirement language

Adopt BCP 14 once at an appropriate shared package or contract scope:

> Uppercase requirement terms use BCP 14 (RFC 2119 and RFC 8174); lowercase words retain ordinary meaning.

MUST and MUST NOT are requirements and prohibitions. SHOULD and SHOULD NOT allow
exceptions only after weighing their consequences. MAY grants a genuine choice;
it does not express uncertainty. Use these terms sparingly for material contract
constraints. Their use and the concise-summary convention here are project
choices, not requirements imposed on PHPDoc by the RFCs.

## Supported types and tags

Keep native types in signatures. Add `@param`, `@return` or `@throws` when they
carry descriptions, refined supported types or necessary metadata. Preserve
necessary analyzer-only annotations. Do not invent types or portable pre/post
tags. Verify shapes, generics and `@phpstan-*`/`@psalm-*` against actual versions.
For a positive finite timeout, use `@param float $timeout Positive, finite execution limit in seconds.`;
do not assume `positive-float` is a supported alias.

Official references: [RFC 2119 §6](https://www.rfc-editor.org/rfc/rfc2119#section-6),
[RFC 8174 §2](https://www.rfc-editor.org/rfc/rfc8174#section-2),
[phpDocumentor DocBlocks](https://docs.phpdoc.org/guide/guides/docblocks.html),
[PHPStan types](https://phpstan.org/writing-php-code/phpdoc-types).
The [PSR-11 specification](https://www.php-fig.org/psr/psr-11/) and
[ContainerInterface 2.0.2](https://github.com/php-fig/container/blob/2.0.2/src/ContainerInterface.php)
illustrate implementation-independent semantics. Their conventions do not justify
copying unrelated obligations or boilerplate into another interface.
