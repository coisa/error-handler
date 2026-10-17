# Release maintenance

Composer derives the `coisa/error-handler` version from Git tags. The private
npm workspace tracks release versions and provides development tooling;
Composer consumers do not execute Node/npm scripts.

Implementation PRs carry an English pending Changeset describing their
consumer-visible change. Use patch for compatible fixes, minor for compatible
additions and major for approved breaking changes. The
[Changesets procedure](../.changeset/README.md) covers note creation, dependency
automation, validation, version PRs, provenance and GitHub publication.

```sh
npm ci --ignore-scripts
npm run changeset:status
```

Changesets collects pending notes in a separate version PR. Review its version
and changelog diff, consumed notes and native checks against the exact commit.
Keep implementation and version PR review distinct.

The owner reviews and merges PRs and authorizes tag/release publication.
Verify the immutable tag points to the reviewed commit and that Packagist's
source/dist references match it. Pages publication follows its own authorization
and workflow. Local status or version rehearsal remains preparation for these
separate decisions.
