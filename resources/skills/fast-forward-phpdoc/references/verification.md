# Verification and reporting

Before accepting an edit:

- Compare the documented promises with the specification, implementations,
  callers and tests. Distinguish intended requirements from observed behavior.
- Substitute a plausible different implementer: interface prose must still hold
  without naming a concrete class, framework or incidental mechanism.
- Check short summaries, useful non-repeated descriptions, valid refinements and
  exception conditions. Report unsupported guarantees or ambiguous contracts.
- Review described parameters and returns, richer types, intentional annotations
  and standalone/inline inheritance markers after the real formatter dry-run.
- When changing cleanup policy, test preservation cases, violations, explicit fix
  and second-pass idempotence; check must leave source unchanged.
- Inspect the actual generated text when rendering or inheritance is affected.
  Exit zero and PHP syntax validation alone do not establish semantic retention.
- Run affected behavioral tests when a contract reveals a real implementation
  defect; keep runtime fixes separate from a documentation-only change.

Use the existing project's commands and PHP support floor. Do not invoke the
legacy `dev-tools phpdoc` command merely because this procedure originated there.
Do not execute untrusted executable configuration with secrets or a write token.

A bounded report identifies changed files and intended semantics, source/tool
versions, commands actually run, their results, and unresolved contracts or
rendering limitations. Manual review, source fixtures, format validation, rendered
output and model task performance are distinct observations. Do not claim that
loading this skill guarantees compliant agent output or that ECS/Rector proves
the truth of normative contracts.
