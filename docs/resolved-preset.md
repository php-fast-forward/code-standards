# Resolved PER preset

Snapshot: ECS 13.3.9 from this repository’s Composer lock. The compiled container
contains 63 distinct PHP-CS-Fixer rules, all reporting `isRisky() === false`.
This is the actual Code Standards preset, not a resolved DevTools or CoiSA preset.

The fixture `tests/Fixtures/preset-rules.json` records the installed ECS version
and exact reference, fully qualified classes, explicit configuration and risky
classification after container boot/skip passes. Unspecified options retain the
fixer's defaults at that exact version; the table does not expand those defaults.
`tests/Config/PresetTest.php` rebuilds that configuration and compares it exactly.
It reads this installation's Composer metadata and verifies every fixer's source
path inside that exact ECS package. This avoids querying the global
`InstalledVersions` registry after the bundled ECS autoloader has registered its
own graph: locally that changed the reported ECS version from `13.3.8` to
`dev-main`. Installation separation alone does not isolate that global registry.
Dependency updates must review any resulting rule/configuration change before
updating the fixture; do not regenerate it merely to silence a failure.

| Rule | Explicit effective configuration |
| --- | --- |
| `array_indentation` | `default` |
| `array_syntax` | `default` |
| `attribute_block_no_spaces` | `default` |
| `binary_operator_spaces` | `{"default":"at_least_single_space"}` |
| `blank_line_after_namespace` | `default` |
| `blank_line_after_opening_tag` | `default` |
| `blank_line_between_import_groups` | `default` |
| `blank_lines_before_namespace` | `default` |
| `braces_position` | `{"allow_single_line_anonymous_functions":false,"allow_single_line_empty_anonymous_classes":true}` |
| `cast_spaces` | `default` |
| `class_definition` | `{"inline_constructor_arguments":false,"space_before_parenthesis":true}` |
| `compact_nullable_type_declaration` | `default` |
| `concat_space` | `{"spacing":"one"}` |
| `constant_case` | `default` |
| `control_structure_braces` | `default` |
| `control_structure_continuation_position` | `default` |
| `declare_equal_normalize` | `default` |
| `elseif` | `default` |
| `encoding` | `default` |
| `full_opening_tag` | `default` |
| `function_declaration` | `{"closure_fn_spacing":"none"}` |
| `indentation_type` | `default` |
| `line_ending` | `default` |
| `lowercase_cast` | `default` |
| `lowercase_keywords` | `default` |
| `lowercase_static_reference` | `default` |
| `method_argument_space` | `{"on_multiline":"ignore"}` |
| `modifier_keywords` | `default` |
| `new_with_parentheses` | `{"anonymous_class":false}` |
| `no_blank_lines_after_class_opening` | `default` |
| `no_break_comment` | `default` |
| `no_closing_tag` | `default` |
| `no_extra_blank_lines` | `{"tokens":["use"]}` |
| `no_leading_import_slash` | `default` |
| `no_multiple_statements_per_line` | `default` |
| `no_space_around_double_colon` | `default` |
| `no_spaces_after_function_name` | `default` |
| `no_trailing_whitespace` | `default` |
| `no_trailing_whitespace_in_comment` | `default` |
| `no_whitespace_in_blank_line` | `default` |
| `nullable_type_declaration` | `default` |
| `operator_linebreak` | `default` |
| `ordered_class_elements` | `{"order":["use_trait"]}` |
| `ordered_imports` | `{"imports_order":["class","function","const"],"sort_algorithm":"none"}` |
| `ordered_types` | `{"null_adjustment":"always_last","sort_algorithm":"none"}` |
| `phpdoc_align` | `{"align":"left"}` |
| `return_type_declaration` | `default` |
| `short_scalar_cast` | `default` |
| `single_blank_line_at_eof` | `default` |
| `single_class_element_per_statement` | `default` |
| `single_import_per_statement` | `{"group_to_single_imports":false}` |
| `single_line_after_imports` | `default` |
| `single_line_empty_body` | `default` |
| `single_space_around_construct` | `{"constructs_followed_by_a_single_space":["abstract","as","case","catch","class","const","const_import","do","else","elseif","enum","final","finally","for","foreach","function","function_import","if","insteadof","interface","match","named_argument","namespace","new","private","protected","public","readonly","static","switch","trait","try","type_colon","use","use_lambda","while"],"constructs_preceded_by_a_single_space":["as","else","elseif","use_lambda"]}` |
| `single_trait_insert_per_statement` | `default` |
| `spaces_inside_parentheses` | `default` |
| `statement_indentation` | `default` |
| `switch_case_semicolon_to_colon` | `default` |
| `switch_case_space` | `default` |
| `ternary_operator_spaces` | `default` |
| `trailing_comma_in_multiline` | `{"after_heredoc":true,"elements":["arguments","array_destructuring","arrays","match"]}` |
| `types_spaces` | `default` |
| `unary_operator_spaces` | `{"only_dec_inc":true}` |

The four preservation skips and callback-last precedence remain declared in the
preset. The native PER set is ECS 13.3.9 `per-cs.php`, explicitly PER Coding Style 3.0 (not the moving PHP-CS-Fixer `@PER-CS` alias and not PER 3.1). Some skipped rules are absent from the PER set; their absence
must not be presented as proof that broader profiles are safe. Native ECS builder
`withSkip()` replaces the builder’s skip list, while configured rules of the same
class use the last configuration. Consumers own their explicit overrides.

## Consumer language compatibility

The formatter's PHP runtime is separate from the consumer's supported PHP versions.
The default deliberately excludes `parameters` from the native multiline trailing
comma rule: commas in parameter declarations require PHP 8.0, while running the
formatter on PHP 8.3 or later does not authorize upgrading a consumer's syntax.
Arguments, arrays, destructuring and match retain the selected native behavior,
including `after_heredoc`. Existing parameter commas are preserved, not removed.
This conservative exception is not a complete PER conformance claim or a PHP 7.4
compatibility certification. Syntax checks use the executing PHP binary.

A consumer that has reviewed PHP 8.0-or-later compatibility can explicitly include
`parameters` through `ECSConfig::configure()`'s native callback. The callback runs
last; it owns the complete `TrailingCommaInMultilineFixer` configuration supplied
through `withConfiguredRule()`. No target version is inferred from the host.
Explicit target selection and execution on consumer PHP versions remain part of
the open compatibility and policy work in issues #2 and #5.

Sources: [PHP parameter syntax](https://www.php.net/manual/en/functions.arguments.php),
[native fixer options](https://cs.symfony.com/doc/rules/control_structure/trailing_comma_in_multiline.html),
and [PER language-version exceptions](https://www.php-fig.org/per/coding-style/#previous-language-versions).

Golden fixtures prove formatting, check-without-writes, exact fixed bytes and
second-pass idempotence. Preservation covers author/package/typed-parameter/
void-return documentation, property visibility, mutable DateTime construction
and the existing copyright/header position/year. No fixture executes source.

Remaining #5 work: resolve DevTools ECS/Rector/PHPDoc and CoiSA against explicit
compatible dependency graphs, compare all rules and header/Finder behavior, and
decide separately authorized opt-in profiles. The CoiSA source snapshot does not
track a Composer lock; an ignored local lock is not a reproducible published
dependency graph. This initial evidence cannot authorize consumer migration.

Reviewed policy update: the attributed-command spacing regression requires the
native method_argument_space multiline policy to preserve deliberate blank
lines. The old snapshot failed on exactly this one option after refreshing the
base; only that reviewed configuration entry was updated. No risk flag changed.


## PHPDoc preservation decision

Keep `NoSuperfluousPhpdocTagsFixer`, `NoEmptyPhpdocFixer`,
`PhpdocNoEmptyReturnFixer` and `GeneralPhpdocAnnotationRemoveFixer` skipped.
The installed NoSuperfluous implementation removes redundant `@final` and
`@abstract` annotations without checking descriptions; its `remove_inheritdoc`,
unused/hidden-parameter and mixed options cannot protect that case. The installed
empty-return implementation removes a void/null annotation by type even when
it carries a description. General annotation removal deletes entire configured
tags. No custom cleanup wrapper is introduced to work around these limitations.
Redundant type-only PHPDoc therefore remains until selective preservation can
be proved; preserving information takes precedence over blanket cleanup.

`PhpdocToCommentFixer` and `GlobalNamespaceImportFixer` are no longer skipped,
but neither belongs to the selected PER set: removing those skips does not
implicitly activate either fixer. A consumer can explicitly select and configure
them through the native builder. In particular, docblock-to-comment conversion
changes analyzer/documentation semantics and is not silently enabled here.
No broad common, docblock, Symfony, risky or migration set is added.

The effective golden fixture preserves simple/multiline descriptions, inline
and standalone inheritance markers, virtual parameters, list/shape/class-string
types, explained unions and void/null returns, author/package/subpackage and
PHPStan/Psalm tags, described final/abstract markers and the pre-return @var.
Both fix idempotence and read-only check bytes are asserted by the real CLI.
No visual IDE equivalence was established, so inheritdoc remains untouched.
