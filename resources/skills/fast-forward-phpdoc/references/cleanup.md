# Cleanup and preservation

Preserve useful local wording, attributes, imports, member order and legal headers.
Do not add a fixed DevTools namespace, header, author or year to consumer files.
Keep tag layout consistent with the consumer's active formatter configuration.

These tags retain information beyond native types:

```php
/** @return int Lifetime in seconds; zero disables expiry. */
/** @param list<string> $names Names in the caller's requested order. */
/** @throws InvalidArgumentException When the supplied name is empty. */
```

The examples are independent annotation snippets, not promises about an existing
API. When moving prose, preserve the full meaning and verify rendered output.
Do not always add or always remove `@return void`: a description can be useful.
Preserve array shapes, generics, tool annotations and intentional package/author
tags. Do not silently delete a described unused/virtual parameter or a described
`@final`/`@abstract` tag just because a fixer calls it redundant.

For interfaces, remove redundant formal filler rather than replacing it with a
longer paragraph. For example, “Reads the configured lifetime.” states a purpose;
“This method MUST read the configured lifetime” repeats it without new semantics.
Do not fabricate deterministic output or failure guarantees from a classifier's
name. Do not repeat a package-wide RFC or license declaration on every method.

## Inheritance

Preserve `{@inheritDoc}` and inherited descriptions until the actual renderer,
analyzer and IDE behavior is qualified. A child summary or description can shadow
parent content. The [phpDocumentor inheritance guide](https://docs.phpdoc.org/guide/guides/inheritance.html)
describes inline inheritance, but a successful build alone does not prove that a
specific renderer/template retained the parent's obligations and exception text.

Inspect the rendered child summary, description, parameters, return and failures
when changing inheritance. A source-preservation fixture proves only source
preservation. Do not promise universal IDE equivalence or mechanically replace a
standalone inheritance marker without checking the target toolchain.

## Automated cleanup

Inspect the effective rules, not just preset labels or skip entries. A removed
skip does not necessarily enable its fixer. Some PHPDoc cleanup rules remove
described return tags or inheritance; retain a skip when supported configuration
cannot preserve the required meaning. Do not enable a whole risky or docblock set
as an innocent formatting change. Validate golden output and a second pass under
the actual installed version, including local overrides.
