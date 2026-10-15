# Release maintenance

This workflow prepares the unreleased major after 2.0.0. Adding it to PR #3 does
not merge that PR, create a tag, publish a release or deploy documentation.

## Contributor flow

Use Node 24 and npm 10.9+ for release maintenance:

```sh
npm ci --ignore-scripts
npm run changeset
npm run changeset:status
npm run test:release
```

Select `@coisa/error-handler`, choose a bump and write an English summary. Commit
the generated `.changeset/*.md` with the implementation. The first pending note
contains this branch's migration and lifecycle changes and requests a **major**
bump from the published 2.0.0 baseline to 3.0.0. Additive APIs normally use minor;
fixes, documentation and dependency maintenance normally use patch. Changesets
combines pending notes using the highest applicable bump.

The npm package is **private development tooling**. Its name maps to the Composer
package `coisa/error-handler`; its version tracks the last stable Git release.
Do not add a `version` field to `composer.json`: Composer obtains versions from
Git tags. Node is unnecessary for installing or using the PHP library. No npm
publication, npm credential or additional PHP runtime package is involved.

The Release tooling workflow checks new/modified notes against the PR base and
rejects an empty body or missing package bump. The bot version PR is exempt
because Changesets consumes those notes. Dependabot PRs need a maintainer-added
note too; this intentionally keeps the initial workflow small and reviewable.
Use the same docs, examples, PHPDoc and consumer-skill requirements for a feature
PR as for any other public API change.

## Version PR and publication

1. A merge into `master` runs the Release workflow. On its initial adoption,
   introducing `package.json` establishes the 2.0.0 baseline and publishes nothing.
2. Pending notes create or update the bot branch `changeset-release/master`.
   `npm run version:packages` updates `package.json`, `package-lock.json` and
   `CHANGELOG.md`, and consumes the notes. It leaves PHP dependency versions alone.
3. Review that version PR, generated notes and exact-head CI. The workflow
   explicitly dispatches PHP CI on the generated branch because PRs created with
   `GITHUB_TOKEN` do not automatically trigger `pull_request` workflows.
4. Merging the reviewed **bot version PR** authorizes publication. The following
   push verifies the changed version, same-repository release branch, bot author,
   merged status and exact merge SHA through GitHub's API.
5. The workflow creates the stable tag, for example `3.0.0`, and a GitHub release
   containing only that version's changelog section. The tag format matches the
   existing `2.0.0` and `1.0.0` tags. An existing matching tag/release is reusable;
   a tag pointing elsewhere, draft conflict or API failure stops publication.

The implementation supports stable versions. Prerelease channels need a separate
design before adoption. Do not change the manifest version in ordinary feature
PRs: publication requires the reviewed bot PR and its matching notes. A workflow
retry can complete a release after a tag was created, without moving that tag.

GitHub Releases and Packagist are separate delivery steps. Packagist discovers Git
tags through the package's configured GitHub hook or a maintainer-triggered
update. Verify that integration in the package settings; this PR neither changes
Packagist settings nor claims a successful registry update.

## Repository setup and permissions

An owner must enable **Actions → General → Allow GitHub Actions to create and
approve pull requests**. Keep the version PR subject to review and required
checks. No auto-merge is configured. The default `GITHUB_TOKEN` is sufficient:
the release job has `contents: write` for tags/commits/releases,
`pull-requests: write` for its version PR and `actions: write` only to dispatch CI.
PR verification remains read-only and never uses `pull_request_target`.

Actions are pinned to immutable commits, npm dependencies are locked, and
installation uses `npm ci --ignore-scripts`. Weekly Dependabot proposals cover
Composer, GitHub Actions and the npm release tool. PHP validation stays in the
lowest/highest PHP 8.3–8.5 matrix. GitHub Pages remains a separate
manual workflow restricted to `master`.

`scripts/release.mjs` has deterministic tests for provenance, version changes,
changelog extraction, conflicting tags, idempotency and API errors. Test and
rehearse `npm run version:packages` in a disposable checkout before changing the
flow. Mocked publication tests do not establish live repository permissions or
Packagist delivery. The first live version PR and publication happen only after
the owner's corresponding merges.

References: [Changesets version action](https://github.com/changesets/action/tree/ae32849d5ba541f9ae29e40e22a623bc13562f51/version),
[Changesets configuration](https://github.com/changesets/changesets/blob/main/docs/config-file-options.md),
[GitHub token workflow triggers](https://docs.github.com/en/actions/how-tos/writing-workflows/choosing-when-your-workflow-runs/triggering-a-workflow),
[Composer library versions](https://getcomposer.org/doc/02-libraries.md#library-versioning).
