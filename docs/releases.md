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

Optional `issue` and `pull_request` fields contain positive integers. Omit
`author`: this repository does not add contributor thanks to new entries.
The folder contains only valid fragments; contribution instructions live here.

No local changelog installation is required. The workflow supplies its trusted
Docker runtime on Linux. If you already use the compatible upstream CLI, its
`check` and `status --json --source=tags` commands validate and preview the plan;
local CLI requirements belong to that tool, not the library's PHP support.
With no pending fragments, the version command succeeds without changing files.

## Contribution checks and feedback

One caller handles the lifecycle on the repository's default branch:

- `pull_request` checks committed contributions with read-only permissions.
- `pull_request_target` uses the pinned runtime for one updated PR comment and
  commit status, without executing consumer PHP, Composer scripts or templates
  from the PR with write credentials.
- Dependabot PRs receive a fragment from verified dependency metadata. Existing
  notes are preserved; review the generated summary and release impact.
- Default-branch pushes and manual dispatch prepare or update `changelog/version`.

The caller has no repository name or default-branch literal. The reusable
workflow selects those from the event. Its implementation is pinned to a
reviewed commit because upstream has no major-version tag at the selected
revision; update the pin deliberately to another integrated revision.

PHP CI, coverage and formatting remain independent checks. The generated-head
changelog status verifies release inputs; it does not run the PHP suite.
With `GITHUB_TOKEN`, GitHub can require owner approval before workflows on a
generated PR run. Approve those runs and verify their exact head before merging.
An optional GitHub App is an application-level authentication choice, described
in the [upstream guide](https://github.com/php-fast-forward/changelog/blob/main/docs/github-app.md).
This workflow configures no App, secret, repository setting or branch rule.

## Review and publication

The version PR consolidates validated fragments into `CHANGELOG.md` and removes
only the consumed notes. Review its next version, text, diff and actual PHP CI
results. New contributions update the same managed PR; its title is not its
identity. Preserve published historical entries and the introductory header.
New sections use Keep a Changelog categories; older Changesets headings remain
part of the historical record.

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
