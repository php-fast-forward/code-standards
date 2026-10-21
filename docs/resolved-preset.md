# Resolved initial preset

Snapshot: ECS 13.3.8 from this repository’s Composer lock. The compiled container
contains 48 distinct PHP-CS-Fixer rules, all reporting `isRisky() === false`.
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
| `binary_operator_spaces` | `default` |
| `blank_line_after_namespace` | `default` |
| `blank_line_after_opening_tag` | `default` |
| `braces_position` | `{"allow_single_line_empty_anonymous_classes":true}` |
| `class_definition` | `default` |
| `concat_space` | `{"spacing":"one"}` |
| `constant_case` | `default` |
| `control_structure_braces` | `default` |
| `control_structure_continuation_position` | `default` |
| `declare_equal_normalize` | `{"space":"none"}` |
| `declare_parentheses` | `default` |
| `elseif` | `default` |
| `encoding` | `default` |
| `full_opening_tag` | `default` |
| `function_declaration` | `default` |
| `indentation_type` | `default` |
| `line_ending` | `default` |
| `lowercase_cast` | `default` |
| `lowercase_keywords` | `default` |
| `method_argument_space` | `{"on_multiline":"ensure_fully_multiline"}` |
| `new_with_parentheses` | `default` |
| `no_blank_lines_after_class_opening` | `default` |
| `no_break_comment` | `default` |
| `no_closing_tag` | `default` |
| `no_extra_blank_lines` | `{"tokens":["curly_brace_block"]}` |
| `no_leading_import_slash` | `default` |
| `no_multiple_statements_per_line` | `default` |
| `no_singleline_whitespace_before_semicolons` | `default` |
| `no_spaces_after_function_name` | `default` |
| `no_trailing_whitespace` | `default` |
| `no_trailing_whitespace_in_comment` | `default` |
| `no_whitespace_before_comma_in_array` | `default` |
| `ordered_imports` | `{"imports_order":["class","function","const"]}` |
| `phpdoc_align` | `{"align":"left"}` |
| `return_type_declaration` | `default` |
| `short_scalar_cast` | `default` |
| `single_blank_line_at_eof` | `default` |
| `single_class_element_per_statement` | `{"elements":["property"]}` |
| `single_line_after_imports` | `default` |
| `single_space_around_construct` | `default` |
| `spaces_inside_parentheses` | `default` |
| `statement_indentation` | `default` |
| `switch_case_semicolon_to_colon` | `default` |
| `switch_case_space` | `default` |
| `ternary_operator_spaces` | `default` |
| `unary_operator_spaces` | `default` |
| `visibility_required` | `{"elements":["const","method","property"]}` |
| `whitespace_after_comma_in_array` | `default` |

The six preservation skips and callback-last precedence remain declared in the
preset. Some skipped rules are absent from this initial PSR-12 set; their absence
must not be presented as proof that broader profiles are safe. Native ECS builder
`withSkip()` replaces the builder’s skip list, while configured rules of the same
class use the last configuration. Consumers own their explicit overrides.

Golden fixtures prove formatting, check-without-writes, exact fixed bytes and
second-pass idempotence. Preservation covers author/package/typed-parameter/
void-return documentation, property visibility, mutable DateTime construction
and the existing copyright/header position/year. No fixture executes source.

Remaining #5 work: resolve DevTools ECS/Rector/PHPDoc and CoiSA against explicit
compatible dependency graphs, compare all rules and header/Finder behavior, and
decide separately authorized opt-in profiles. The CoiSA source snapshot does not
track a Composer lock; an ignored local lock is not a reproducible published
dependency graph. This initial evidence cannot authorize consumer migration.
