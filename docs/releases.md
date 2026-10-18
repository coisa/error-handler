# Release notes and reviewed releases

The [Changelog workflow](https://github.com/coisa/error-handler/blob/master/.github/workflows/changelog.yml)
uses [Fast Forward Changelog](https://github.com/php-fast-forward/changelog) for
independent release notes, PR checks and a reviewed version PR. Git tags are the
version authority for `coisa/error-handler`; there is no separate npm manifest
or Composer `version` field to maintain.

## Add a release note

Create a unique lowercase dash-separated file in `.changelog/`, for example
`.changelog/preserve-reporting-order.md`:

```markdown
---
category: fixed
type: patch
---

Preserve reporter order when handling an application exception.
```

Describe the observable change in English. Commit the fragment with the change;
keep meaningful Markdown indentation, code fences and hard line breaks. Prefer
a backslash at the end of a line for an explicit Markdown break: Git's unchanged
whitespace check rejects trailing spaces, even when intended as a hard break. Ordinary
contributions leave existing fragments and `CHANGELOG.md` intact.

Categories are `added`, `changed`, `deprecated`, `removed`, `fixed` and `security`.
Always specify `type`: `patch` for compatible fixes or maintenance, `minor` for
compatible additions, and `major` for breaking changes. A tooling change or an
upstream dependency's major version does not itself require a library major.
The 2.x maintenance line preserves its public API and PHP requirements.

PR references are discovered automatically after contributions are merged.
Write the fragment before opening its PR; no follow-up edit is needed to add
the number. An optional `pull_request` field can record an already verified PR
number. Never infer it from an issue, branch name or commit message. Omit
`issue` and `author` in ordinary notes: entries use a delivering PR link when
known, with no automatic thanks or commit-link substitute.
The folder contains only valid fragments; contribution instructions live here.

No local changelog installation is required. The workflow supplies its trusted
Docker runtime on Linux. If you already use the compatible upstream CLI, its
`check` and `status --json --source=tags` commands validate and preview the plan.
Local CLI requirements belong to that tool, not the library's PHP support.
Local commands leave PR-origin discovery disabled unless
`--resolve-pull-requests` is selected; use it when rehearsing the workflow's
plan in a disposable copy with authorized read-only GitHub access. `source: tags`
selects historical notes and does not disable PR-origin API reads.
With no pending fragments, the version command succeeds without changing files.

## Project tooling skill

The repository includes the upstream `fast-forward-changelog` skill under
`.agents/skills`, installed with the CLI's native project-scoped installer. Its
receipt records the source release and payload hashes. It is separate from the
library's bundled `coisa-error-handler` consumer skill and is excluded from the
Composer distribution.

For the reviewed release, use [CPX](https://github.com/laravel/cpx) with the exact
numeric Composer version, without a `v` prefix:

```sh
CHANGELOG_VERSION=0.2.0

cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" help skill:install
cpx --skip-local "fast-forward/changelog:$CHANGELOG_VERSION" skill:install --check
```

Run these commands from the repository root. Inspect the selected release's help
before installing or updating; preserve local changes and let its installer
manage the payload and receipt together. A fresh project installation uses
`skill:install` without `--check`. Do not modify the upstream copy directly or
install it globally as part of repository maintenance. Installation and a clean
receipt check do not establish activation in a particular agent session.

This release tool requires PHP 8.5. CPX isolates its Composer dependencies but
does not supply a PHP runtime. The workflow uses upstream's Docker runtime;
library users retain the declared PHP 7.4+ support. PHP CI checks library-owned
files on each supported runtime and the upstream skill's PHP example on 8.5.

## Central history format

`CHANGELOG.md` uses English [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
headings and [Semantic Versioning](https://semver.org/spec/v2.0.0.html). Retain one
title and introduction. Published versions use `## [version] - YYYY-MM-DD`,
newest first, with their verified publication dates; do not substitute commit
dates or today's date when historical evidence is missing.

Classify entries by behavior under Added, Changed, Deprecated, Removed, Fixed
or Security, in that order, omitting empty categories. The category describes
the change; fragment `type` separately selects its SemVer impact. Keep Markdown
paragraphs, lists and code fences intact. A verified delivering PR is linked
inline as `([#123](https://github.com/coisa/error-handler/pull/123))`. Without
verified PR evidence, leave only the description; do not add author thanks,
issues or commit hashes as substitutes.

The footer contains version link definitions in the same newest-to-oldest order
as the sections. Each later version compares its preceding tag with that tag;
the first version links to its own release/tag. Preserve correct existing
destinations. Pending contributions live in fragments, so consolidation adds
no new `Unreleased` section. Historical normalization is an explicit maintenance
task, not part of ordinary contribution editing.

## Contribution checks and feedback

One caller handles the lifecycle on the repository's default branch:

- `pull_request` checks committed contributions with read-only permissions.
- `pull_request_target` uses the upstream runtime for one updated PR comment and
  commit status, without executing consumer PHP, Composer scripts or templates
  from the PR with write credentials.
- Dependabot PRs receive a fragment from verified dependency metadata. Existing
  notes are preserved; review the generated summary and release impact.
- Default-branch pushes and manual dispatch prepare or update `changelog/version`.

The caller has no repository name or default-branch literal. The reusable
workflow selects those from the event. It retains the lifecycle's default
`resolve-pull-requests: true` consistently across preparation, generated-head
checks and publication. The upstream runtime verifies exact fragment origins and
merged-PR repository/ancestry evidence without modifying fragment metadata.
No qualifying merged PR leaves the description alone; ambiguous or unavailable
evidence blocks preparation rather than inventing a reference.

The workflow follows upstream's official `v0` compatibility branch. This is a
branch/channel, not a version tag; the reviewed implementation matches published
`v0.2.0`. Minor updates in the `0.x` channel can change contracts. Verify the
channel's resolved commit and compatible release contract when investigating a
run or updating the integration.

Review the [upstream lifecycle contract](https://github.com/php-fast-forward/changelog/blob/v0.2.0/docs/reusable-lifecycle.md)
before changing the workflow's runtime reference or settings. The caller supplies
no consumer PHP, Composer or Node runtime and keeps its current Linux runner and
job-scoped permissions. Do not add privileged execution of PR code.

PHP CI, coverage and formatting remain independent checks. The generated-head
changelog status verifies release inputs; it does not run the PHP suite.
With `GITHUB_TOKEN`, GitHub can require owner approval before workflows on a
generated PR run. Approve those runs and verify their exact head before merging.
An optional GitHub App is an application-level authentication choice, described
in the [upstream guide](https://github.com/php-fast-forward/changelog/blob/v0.2.0/docs/github-app.md).
This workflow configures no App, secret, repository setting or branch rule.

Central-history maintenance requires the reusable's verified maintain/admin
authorization, including the actual granting actor. A label name by itself does
not authorize an edit or waive validation. Keep the migration's maintenance
grant separate from the new fragment, independent PHP checks and publication.

## Review and publication

The version PR consolidates validated fragments into `CHANGELOG.md` and removes
only the consumed notes. Review its next version, text, diff and actual PHP CI
results. New contributions update the same managed PR; its title is not its
identity. Preserve historical descriptions, verified dates, links and the
introductory header. Automatic PR discovery adds verified links in memory;
failed origin checks require investigation, not manual fabricated associations.
Let the workflow regenerate an existing managed version PR after changed settings
or history reach the default branch; do not overwrite its signed head or trailers.

The workflow retains automatic publication after the owner merges the managed
version PR. `publish: true` routes that approved merge to the upstream publisher,
which validates the exact merge SHA and consolidation evidence before creating
its `v`-prefixed Git tag and GitHub Release. It does not publish an npm package,
merge PRs or bypass review. Composer consumers receive versions through Git tags
and the existing package registry integration.

Preparing this integration or passing CI does not authorize an agent to merge a
PR or publish a release. Test consolidation only in a disposable copy. For a
failed publication, inspect the run and its exact SHA; upstream can complete a
matching partial publication without moving an existing tag. Never move or
delete published tags to repair a prepared version.

## Existing GitHub Releases

The approved central history is the canonical source of each Release's notes.
Use the compatible CLI's `notes <version>` command to extract that version,
preserving the Markdown and the reference context needed by its links. Compare
each proposed body with its current Release before requesting synchronization.
Inspect the extracted body for unresolved reference links and include only the
definitions needed by that body when preparing its standalone Release notes.
Do not attach the entire changelog, an author list or GitHub's generated commit
summary.

Historical reconciliation is separate from the normal publisher. A divergent
existing Release is not silently rewritten by merging a contribution. Prepare
updates, and missing Releases only for verified tags, for owner approval first.
After the approved history is integrated and synchronization is authorized,
revalidate its exact SHA and reread every changed Release. Preserve tags, target
commits, assets and draft/prerelease flags; do not move tags or backdate a Release
created during reconciliation.
