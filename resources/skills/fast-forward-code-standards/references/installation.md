# Optional skill installation and lifecycle

The complete payload ships at
`vendor/fast-forward/code-standards/resources/skills/fast-forward-code-standards`.
`extra.skills.source` advertises it; suggesting `llm/skills` installs or approves
nothing. Loading a skill does not install the PHP tool. Default to project scope
for reviewable team instructions; global copies are an explicit consumer choice.

## Optional LLM Skills project route

Before first approved install/sync, merge this `skills.json`, preserving existing
manual settings and aliases:

```json
{
  "target": ".agents/skills",
  "aliases": [],
  "discovery": false,
  "auto-sync": true,
  "vendor-sources": false,
  "dependencies": {
    "composer": {
      "trusted": ["fast-forward/code-standards"],
      "trusted-replace": true
    }
  }
}
```

With explicit project-plugin approval:

```sh
composer config allow-plugins.llm/skills true
composer require --dev 'llm/skills:^1.15'
composer skills:show
```

Preserve other `allow-plugins` entries; the donor grants no approval. This narrow
allowlist selects all bundled skills in the installed Code Standards donor,
including PHPDoc. Organization-wide `fast-forward/*` trust is a broader choice.
Commit intended manifest/lock/settings according to the consumer's policy.

For explicit-only sync set `auto-sync:false` before enabling the plugin:

```sh
composer skills:update fast-forward/code-standards --dry-run
composer skills:update fast-forward/code-standards
```

Named donor selection intentionally trusts that donor for the run. With 1.15
behavior, `--no-scripts` alone does not suppress plugin sync; use the reviewed
`auto-sync:false` policy or `--no-plugins`. Inspect copied bytes and tool output;
Composer success alone does not prove complete installation.

## Manual copy, updates and removal

A requested manual project copy refuses an existing destination or symlink:

```sh
skill_source="vendor/fast-forward/code-standards/resources/skills/fast-forward-code-standards"
skill_target=".agents/skills/fast-forward-code-standards"
test -f "$skill_source/SKILL.md" &&
test ! -e "$skill_target" && test ! -L "$skill_target" &&
mkdir -p "$(dirname "$skill_target")" && cp -R "$skill_source" "$skill_target"
```

Keep LICENSE and all relative references. For explicitly chosen global scope,
use the host's configured home discovery root. Never link into replaceable
`vendor/`. A missing source requires inspecting the installed version/archive,
not a placeholder or implicit dependency upgrade.

Sync replaces same-path edits; extra local files and files removed upstream can
remain. Removing the donor/plugin leaves copies behind. Treat copies as
donor-managed; keep local instructions in a separately named skill. Record
source versions and inspect update diffs.

Before update/removal, inventory and archive only the exact copy outside
discovery. Install the reviewed replacement if requested; restore the complete
backup to roll back. Do not remove handwritten skills or create blanket cleanup
hooks. `composer skills:update --clean --dry-run` is preview only: unscoped clean
can delete the entire target; scoped clean cannot identify an already removed
donor. Cleanup needs a separately reviewed scope.

Inspect files, refresh the host session and confirm discovery of
`fast-forward-code-standards`. Copy, tool listing, host activation and a successful
bounded consumer task are separate observations.
