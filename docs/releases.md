# Release maintenance

This draft reconciles the unreleased major with the already delivered 2.x line.
`package.json`, `package-lock.json` and `CHANGELOG.md` retain the current master
version. The pending major Changeset describes breaking PHP, lifecycle, container
and HTTP changes; it does not publish a 3.x package or replay consumed 2.x notes.

The canonical [Changesets procedure](../.changeset/README.md) documents validation,
version PRs, provenance and native GitHub tag/release boundaries. Node/npm are
development release tooling only; Composer consumers do not execute them.

```sh
npm ci --ignore-scripts
npm run changeset:status
```

Ordinary implementation PRs carry an English pending Changeset. Changesets groups
notes in a separate version PR; the owner reviews/merges that PR and authorizes
the corresponding release. This reconciliation does not invoke a publisher,
merge any PR, create a tag, change Packagist settings or deploy documentation.
The former custom `scripts/release.mjs` prototype and its test harness are not
restored: master already uses the reviewed native Changesets workflow.
