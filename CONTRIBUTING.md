# Contributor dependencies

This library intentionally does not track `composer.lock`. Composer constraints
in `composer.json` describe its supported dependency contract. Applications that
consume the library own their application lockfiles.

Resolve development dependencies with the PHP runtime intended for the check:

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist

composer validate --strict --no-check-publish
composer check-platform-reqs

composer --no-plugins check
```

Composer creates an ignored local lockfile. Keep it to repeat that local
resolution with `composer install --no-plugins --no-scripts`; use `composer update`
when deliberately testing a fresh dependency resolution. Record `php --version`,
`composer --version` and `composer show --locked` with validation evidence.
Do not force-add the local lockfile to a PR.

Development integrations can have narrower PHP requirements than the library's
runtime `^7.4 || ^8.0`. Resolve them on a compatible runner and verify the supported
minimum runtime through a separate consumer without development dependencies.
Do not bypass platform requirements. The existing quality-toolchain baseline is
tracked in [issue #10](https://github.com/coisa/error-handler/issues/10); removing
the lockfile does not repair those independent findings.

Dependency installation in a fresh checkout resolves a local lockfile; subsequent
installs can reuse it. CI must record the actual resolution for its runner, and
cache keys must not require a committed Composer lockfile.

`package-lock.json` remains tracked for the independent Node release tooling.
Node.js is not a runtime dependency of the Composer library.
