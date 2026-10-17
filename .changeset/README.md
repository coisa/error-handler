# Release notes and version PRs

Changesets is development tooling for the Composer package `coisa/error-handler`.
The private npm workspace `@coisa/error-handler` tracks the release version.
It is never published to npm. Composer continues to derive its
version from Git tags; do not add a `version` field to `composer.json`.

Installing or using the PHP library does not require Node.js. The tooling,
lockfile and workflows are excluded from Composer distribution archives.

## Add a release note

Use Node.js 24 and npm 10.9 or newer for maintenance:

```sh
npm ci --ignore-scripts

git fetch origin

npm run changeset

npm run changeset:status
```

Select `@coisa/error-handler`, choose the appropriate bump, and describe the
consumer-visible change in English. Commit the generated `.changeset/*.md` file
with the change. Use `patch` for compatible fixes or maintenance and `minor` for
compatible additions. Changes to public contracts or supported PHP versions need
separate major-release planning.

Keep `package-lock.json` committed to make development dependencies reproducible.
Do not run the version command as part of an ordinary feature PR.
Local status compares runtime files (`src/` and `composer.json`) against the
configured base (`origin/master` in this repository). CI selects the repository's
default branch automatically.
Version PRs can therefore validate after consuming their notes. Tooling-only or
documentation-only PRs may omit a note when no version bump is intended.

The **Changesets** workflow's status and comment jobs follow the official
[non-blocking status example](https://changesets.dev/guide/automating#non-blocking).
It uses `changesets/action/pr-status` and `changesets/action/pr-comment` to create
or update one informational comment, skipping `changeset-release/` branches.
No Changeset bot installation is needed. A read-only job checks out the trusted
base and reads PR files without executing them; a separate job can only post the
comment. It never installs dependencies or runs PHP or npm scripts from the PR.

## Dependabot updates and their release notes

Dependabot checks Composer, the private npm tooling and GitHub Actions weekly,
with at most three open version-update PRs per ecosystem. Each update remains a
separate PR; there is no automatic approval or merge. Composer major updates are
excluded from these maintenance proposals and need an explicit compatibility
decision. npm and action majors are reviewable development-tooling proposals;
they do not automatically mean a major release of this PHP library.

Composer uses `versioning-strategy: widen` to retain already supported dependency
versions rather than raising their minimum. This works with a manifest-only
library: the automation detects `composer.json` updates without requiring a
tracked `composer.lock`. Releases already allowed by a constraint do not need a
new manifest-only PR. Without a committed Composer lockfile, this setup does not
track a fixed transitive dependency graph or promise transitive security-update
PRs. Consumer applications resolve and audit their own installations. See
[Dependabot versioning strategies](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference#versioning-strategy).

The same **Changesets** workflow's Dependabot job writes
`.changeset/dependabot-<PR number>.md` directly with `printf`. It reads the package
name from the default branch's `package.json` and uses the PR title as the summary
with a default `patch` classification. This job does not install Node/npm or run
the Changesets CLI. Each run replaces the same file; identical contents produce
no new commit. Manual edits to that note, including its release classification,
are overwritten on the next run. Other note files are left alone.

A small `gh` check skips stale/closed PRs. The job identifies these PRs by the
`dependabot/` head-branch prefix, without checking the author's login or ID. It
writes only to branches in this repository.

[`stefanzweifel/git-auto-commit-action@v7`](https://github.com/stefanzweifel/git-auto-commit-action)
commits only that PR's generated note after the workflow switches to the checked
PR commit. No PR code or tooling runs after that switch. Checkout retains the
built-in token only in this job so the action can push; no additional secret is
required. A rebase that removes the note gets it back at the same path on its next
event. Review dependencies and make final release adjustments after the last
dependency update.

The generated commit has a bot DCO sign-off and `[dependabot skip]`, allowing
[later rebases](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/manage-your-dependency-security/manage-dependabot-prs#allowing-dependabot-to-rebase-and-force-push-over-extra-commits).
The job uses the existing `pull_request_target` event, following
[Dependabot's write-access guidance](https://github.com/dependabot/fetch-metadata#enabling-auto-merge),
and executes only trusted default-branch code. Informational status and comment
jobs skip `dependabot/` branches because the writer already creates the note; they
do not depend on the writer. The general Changesets validation remains independent.
No PR code is executed by the writer.

This activates after merge. Observe the first real bot PR and rebase then; local
checks do not establish hosted write permissions. `GITHUB_TOKEN` commits
[do not trigger ordinary PR workflows](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/trigger-a-workflow#triggering-a-workflow-from-a-workflow),
so the generated commit does not automatically get another validation run. The
existing `workflow_dispatch` trigger can validate the updated branch manually.
The initial run belongs to its original SHA. General dependency/PHP CI remains
separate from this minimal release-note job.

## Review the version PR

After changes reach the default branch and validation passes, the version job
creates or updates `changeset-release/<default-branch>`. Changesets consumes pending notes, updates
`package.json`, and generates `CHANGELOG.md`. The version script also updates
`package-lock.json` so both manifests have the same version.

The title includes the repository and recalculated version, for example
`chore(release): release coisa/error-handler 2.0.1`. The action locates the open
PR by its head and base branches, not its title. Further merged changes update
that same PR and its title, even when they change the planned release version.
The `chore(release)` commit records release preparation without classifying it
as another runtime feature or bug fix.

Changelog entries use the official
[`@changesets/changelog-github`](https://changesets.dev/packages/changelog-github)
formatter with links to PRs and commits. Author acknowledgements are disabled
with `disableThanks: true`. It reads the
repository from `GITHUB_REPOSITORY`, which Actions provides automatically, and
uses `GITHUB_TOKEN` for read-only GitHub queries. The validation rehearsal passes
the built-in token; the version action passes its token to the version command.
No personal token, custom template or repository name in the config is needed.

Review the version, changelog and consumed notes before merging that PR. The
workflow needs the repository setting **Settings → Actions → General → Workflow
permissions → Allow GitHub Actions to create and approve pull requests** enabled.
It uses the built-in `GITHUB_TOKEN`; no personal token or npm credential is needed.

The validation job installs the locked tooling, validates pending notes
and uses `changesets/action/select-mode` to rehearse only pending releases.
The version job also dispatches validation on the generated branch, without relying
on automatic `pull_request` events from `GITHUB_TOKEN` or on a separate PHP CI.
The validation job has read-only repository access. The version job can write
version metadata, manage its PR and dispatch validation; the publication job
can create tags and GitHub releases.
All six jobs live in `.github/workflows/changesets.yml`. PR checks run on
`pull_request`; the status jobs use `pull_request_target` without executing PR
code. Only pushes to the default branch can prepare a version PR. Dispatched runs validate
the generated branch without starting another version job.

The workflow follows the official [Git tags only recipe](https://changesets.dev/guide/automating#publish-git-tags-only):
Node.js 24, a private package, `privatePackages.tag: true`, and the official
`select-mode`, `version` and `publish` actions. Permissions start empty and are
granted per job; jobs that write to GitHub disable package-manager caching.
This Composer library needs no npm build or publishing credentials.
The explicit validation dispatch avoids requiring an additional GitHub App token.
Actions use the publishers' major references (`@v7` and `@v2`) so compatible action
updates do not require a dependency-update PR for every patch or minor release.
The version PR title, commit message and description use `github.repository`
instead of a hardcoded repository name. The workflow uses
`github.event.repository.default_branch` for event selection, status comparisons,
the stale-run guard and the generated validation branch.

## Merge to release

Merging the generated version PR is the release decision. When no pending notes
remain and the private package has an unpublished tag, the official
`select-mode` action selects `publish`. The publication job checks out the
event's commit and calls the official `changesets/action/publish` action.
There is no custom release script.

Concurrency uses `queue: max` with cancellation disabled, retaining up to 100
pending runs per group. Another push therefore does not replace a pending
release run. Publication uses that run's validated commit even if the default
branch has advanced; version-PR preparation still skips outdated commits.
See GitHub's [concurrency queue documentation](https://docs.github.com/en/actions/how-tos/write-workflows/choose-when-workflows-run/control-workflow-concurrency#example-queueing-multiple-pending-runs).

The official publisher creates `v2.0.1`-style tags and a GitHub release from the
matching changelog entry. Existing `1.0.0` and `2.0.0` tags remain unchanged;
Composer accepts the `v` prefix on new versions. Review version changes through
the generated PR; GitHub records who merged it in the PR timeline and its
`merged_by` API field.

`create-github-releases: true` is explicit. `push-git-tags` is omitted because
the action already defaults it to `true`. No package is sent to npm. Packagist
discovers the Composer version from the Git tag through the repository's
existing integration; this workflow does not configure or force a Packagist sync.

Version commits use the default GitHub API transport, which signs them with
GitHub's key and attributes them to the token owner. `push-with-git-cli` changes
that transport; it does not identify who merges the PR. See the official
[version action inputs](https://github.com/changesets/action/tree/v2/version).

Existing tags are excluded from the official publication plan. If a run stops
after creating a tag but before creating its GitHub release, inspect that tag
and finish the missing release with the matching changelog entry; this minimal
workflow does not add a separate recovery implementation.

The first live version PR and release must still be observed after an
owner-authorized merge. Review prerelease needs separately before adding a
prerelease policy to this stable 2.x flow.

## Maintain the changelog introduction and history

Keep the changelog title, introduction and historical backfills on the default
branch. The generator preserves that introduction and inserts new entries before
the first version heading. Use plain headings such as `## 2.0.0`, with release
links or other metadata below them.

Edits made only to a generated version PR can be overwritten when it is rebuilt
from the default branch. Backfill only published versions; pending versions
belong to their Changesets. A documentation-only backfill does not need a new
Changeset when no additional version bump is intended.

## Rehearse locally

In a disposable checkout with the tooling installed, provide
`GITHUB_REPOSITORY=coisa/error-handler` and a read-capable `GITHUB_TOKEN` through
your environment, then run:

```sh
npm run changeset:status

npm run version:packages

git diff -- package.json package-lock.json CHANGELOG.md .changeset
```

Never commit the token. Adding notes and running `changeset:status` do not need
a GitHub token.

With only the initial maintenance note, expect `2.0.1` in both manifests, a patch
changelog entry, and removal of that note. `composer.json` and `src/` must remain
unchanged; any ignored local `composer.lock` is outside release preparation.
A second run with no pending notes must not bump
the version again; Changesets 3 exits with code 1 for that no-op. The validation
job skips this command when the release plan is empty. Do not commit a local
rehearsal into a feature PR.
