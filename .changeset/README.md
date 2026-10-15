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

git fetch origin master

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
Status compares runtime files (`src/` and `composer.json`) against `origin/master`.
Version PRs can therefore validate after consuming their notes. Tooling-only or
documentation-only PRs may omit a note when no version bump is intended.

## Review the version PR

After changes reach `master`, the **Version PR** workflow creates or updates
`changeset-release/master`. Changesets consumes pending notes, updates
`package.json`, and generates `CHANGELOG.md`. The version script also updates
`package-lock.json` so both manifests have the same version.

Review the version, changelog and consumed notes before merging that PR. The
workflow needs the repository setting **Settings → Actions → General → Workflow
permissions → Allow GitHub Actions to create and approve pull requests** enabled.
It uses the built-in `GITHUB_TOKEN`; no personal token or npm credential is needed.

The **Changesets** workflow installs the locked tooling, validates pending notes
and rehearses versioning when there are pending releases. The version workflow also
dispatches this check on the generated branch, so validation runs without relying
on automatic `pull_request` events from `GITHUB_TOKEN` or on a separate PHP CI.
The validation job has read-only repository access. Only the version job can
write repository contents, manage its PR and dispatch validation.

This step prepares reviewed versions only. It does not create Git tags or GitHub
releases, publish to npm, or change the existing Composer release process.
There is no publishing command configured; with no pending release notes, the
action leaves the repository unchanged.

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
workflow skips this command when the release plan is empty. Do not commit a local
rehearsal into a feature PR.
