# Pending release notes

This repository uses Changesets for the Composer package `coisa/error-handler`.
The private npm manifest is development tooling only; its version mirrors the
latest stable Git tag. Nothing is published to npm, and Composer has no hardcoded
`version` field.

Run `npm ci --ignore-scripts`, then `npm run changeset`. Select
`@coisa/error-handler`, choose patch/minor/major and write an English summary.
Commit the generated Markdown note with the implementation. Documentation and
dependency updates can use patch notes; compatibility breaks require major.

After merge into `master`, automation maintains `changeset-release/master`.
Reviewing and merging that version PR authorizes its Git tag and GitHub release.
See [the release procedure](../docs/releases.md) for permissions, checks and
Packagist delivery. Do not consume pending notes manually.
