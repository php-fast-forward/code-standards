# Copyable workflow callers

`code-standards.yml` is the canonical consumer caller template. Copy it to the
consumer's `.github/workflows/code-standards.yml`, then review the published
reference and inputs. Future DevTools orchestration may copy this resource from
the installed package. The complete `resources/` tree is part of distribution.

The executable reusable remains at `.github/workflows/code-standards.yml` in
this repository, with its supporting check and analysis workflows. GitHub
resolves reusable `uses:` paths there; a file in `resources/` cannot replace
that endpoint. The template calls that endpoint rather than duplicating its
implementation. This matches Changelog's `resources/workflows/changelog.yml`
caller and `.github/workflows/changelog.yml` executable contract.

No installer or workflow synchronization runs on the consumer automatically.
