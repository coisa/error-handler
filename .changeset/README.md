# Release notes and version PRs

Changesets is development tooling for the Composer package `coisa/error-handler`.
The private npm manifest starts at the published `2.0.0` baseline and tracks the
next version. It is never published to npm. Composer continues to derive its
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
separate major-release planning. This setup includes only a maintenance patch.

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
All five jobs live in `.github/workflows/changesets.yml`. PR checks run on
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

## Rehearse locally

In a disposable checkout with the tooling installed, run:

```sh
npm run changeset:status

npm run version:packages

git diff -- package.json package-lock.json CHANGELOG.md .changeset
```

With only the initial maintenance note, expect `2.0.1` in both manifests, a patch
changelog entry, and removal of that note. `composer.json`, `composer.lock` and
`src/` must remain unchanged. A second run with no pending notes must not bump
the version again; Changesets 3 exits with code 1 for that no-op. The validation
job skips this command when the release plan is empty. Do not commit a local
rehearsal into a feature PR.
