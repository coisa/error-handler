# Optional consumer agent skill

The Composer library ships the complete
[`coisa-error-handler` package](../resources/skills/coisa-error-handler/SKILL.md)
under `resources/skills/coisa-error-handler`, including its MIT license,
references and executable application examples. It supports the library's
`^2.4` API and PHP `^7.4 || ^8.0`. Earlier API versions are outside that skill
contract. JSON/Whoops/provider routes were published in 2.5.0 and require `^2.5`;
the common skill baseline remains `^2.4`. Inspect `composer show coisa/error-handler` and the actual files;
do not upgrade a production dependency just to obtain instructions.

Shipping a skill does not install it in a discovery directory or activate it in
an agent session. Composer's `suggest` entry installs nothing automatically.
Choose one copy per consumer/host and preserve its source link and license.
Project scope is the default for reviewable team configuration; global scope is
an explicit consumer choice.

## Optional Composer project synchronization

`llm/skills ^1.15` is optional development tooling and requires PHP 8.2+ with
Composer 2. It does not change this library's PHP 7.4 runtime contract. A consumer
on an earlier PHP can use the manual route below. The library declares
`extra.skills.source: resources/skills` but does not require or approve the
plugin on the consumer's behalf.

Before installing/enabling the plugin or running its first sync, merge this
configuration into the consumer's `skills.json`, preserving existing intentional
settings:

```json
{
  "target": ".agents/skills",
  "aliases": [],
  "discovery": false,
  "auto-sync": true,
  "vendor-sources": false,
  "dependencies": {
    "composer": {
      "trusted": ["coisa/error-handler"],
      "trusted-replace": true
    }
  }
}
```

The allowlist selects this donor; `trusted-replace:true` replaces implicit
dependency trust. `discovery:false` restricts discovery and
`vendor-sources:false` disables dependency-advertised remote sources.
`aliases:[]` requests no extra host directory links; preserve any intentional
aliases in an existing application. A Claude Code consumer may choose
`.claude/skills` as its target instead. Check the actual host's configured
discovery directories.

With the consumer's authority to install and execute that project plugin:

```sh
composer config allow-plugins.llm/skills true
composer require --dev 'llm/skills:^1.15'
composer skills:show
```

Preserve every unrelated `allow-plugins` entry. Prepare root approval before
noninteractive installation; a donor cannot grant it. Commit intended application
Composer files/lockfile and `skills.json` according to the application's policy.
Normal approved install/update operations synchronize the selected installed
donor; no extra post-install hook is needed.

For manual synchronization only, set `auto-sync:false` before installation:

```sh
composer skills:show
composer skills:update coisa/error-handler --dry-run
composer skills:update coisa/error-handler
```

Naming a donor explicitly selects/trusts it for that run; it does not prove the
unfiltered automatic policy. Composer's
[`--no-scripts`](https://getcomposer.org/doc/03-cli.md#global-options) skips
scripts defined in `composer.json`; plugin event listeners can still run.
With Composer 2.10.3 and llm/skills 1.15.0, automatic copying still ran with that
flag. Use `auto-sync:false` to disable only this tool's automatic synchronization.
`--no-plugins` prevents loading all Composer plugins, including unrelated ones.
Inspect both the report and destination bytes.

## Updates, removal and recovery

Treat synchronized copies as donor-managed. Ordinary 1.15 synchronization
replaces incoming same-path files, overwriting local edits, while additional
local files and files removed upstream remain. Removing the donor or plugin
leaves copied skills behind. A missing/moved donor source is not removal of an
old installed copy. Keep intentional local guidance in a separately named,
consumer-owned skill and review donor/tool version changes.

Preview clean behavior before selecting any cleanup:

```sh
composer skills:update --clean --dry-run
```

An unscoped clean can delete all installed skills in the target, including
handwritten packages; noninteractive runs need not prompt. A scoped clean
reinstalls selected skills and cannot identify a donor already removed from
dependencies. Do not add a blanket clean hook. Inventory, archive the exact stale
package outside discovery and inspect the dry run before an authorized cleanup.

## Guarded manual project or global copy

Use the complete package from an installed distribution or an inspected immutable
repository revision. From the consumer root, for project Codex use:

```sh
skill_source="vendor/coisa/error-handler/resources/skills/coisa-error-handler"
skill_target=".agents/skills/coisa-error-handler"

test -f "$skill_source/SKILL.md" &&
test ! -e "$skill_target" &&
test ! -L "$skill_target" &&
mkdir -p "$(dirname "$skill_target")" &&
cp -R "$skill_source" "$skill_target"
```

The guards refuse an existing destination and a dangling symlink. A missing
source calls for inspecting the selected installed version/archive, not creating
an empty placeholder. For explicit global Codex use choose
`$HOME/.agents/skills/coisa-error-handler`; for Claude Code choose the project's
`.claude/skills/coisa-error-handler` or its explicit home counterpart. Preserve
configured locations and avoid duplicate copies. Do not symlink into replaceable
Composer `vendor/` content.

For an update or removal, move only the selected existing package to a unique
backup outside discovery:

```sh
skill_backup="$HOME/.agent-skill-backups/coisa-error-handler-$(date +%Y%m%d%H%M%S)-$$"

test -d "$skill_target" &&
test ! -L "$skill_target" &&
test ! -e "$skill_backup" &&
test ! -L "$skill_backup" &&
mkdir -p "$(dirname "$skill_backup")" &&
mv "$skill_target" "$skill_backup"
```

Moving out removes the manual copy from discovery. For an update, review the new
source and repeat the guarded copy. For rollback, archive any replacement first
and restore the complete saved package to its original path. Never store backups
under an agent discovery root. An installer-managed copy can return on the next
sync until the application's donor/trust settings change.

Installing a Composer plugin globally is not installing skill files in a host's
global discovery root. This guide needs no global plugin or host configuration
changes.

## Verify the selected result

Compare the source and copied file inventory, including references, examples and
license. Refresh the agent session and confirm that it discovers the exact public
name `coisa-error-handler`. Then perform a bounded synthetic integration task and
run the [bundled example](../resources/skills/coisa-error-handler/examples/README.md)
against the application's explicit autoloader. File presence, native discovery
and successful task execution are separate observations.

See first-party [llm/skills documentation](https://github.com/roxblnfk/skills/tree/1.15.0),
[Composer suggestions](https://getcomposer.org/doc/04-schema.md#suggest) and
[plugin approval](https://getcomposer.org/doc/06-config.md#allow-plugins) for the
corresponding tool contracts.
