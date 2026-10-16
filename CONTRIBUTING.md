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

## Production container regression

The public container requires the `psr/container` interface package at runtime;
it does not require another container implementation. Its existing `string $id`
parameters support PSR-11 `^1.1 || ^2.0`. Version 1.0 has untyped parameters and
cannot be implemented by those narrower signatures.

Run the production smoke test against a clean consumer built from the committed
Composer distribution, without development dependencies or an application
package that could incidentally supply PSR-11:

```sh
repository="$PWD"
fixture=$(mktemp -d)
mkdir "$fixture/package" "$fixture/consumer"
git archive HEAD | tar -x -C "$fixture/package"
export COMPOSER_HOME="$fixture/composer-home"
export COMPOSER_CACHE_DIR="$fixture/composer-cache"
cd "$fixture/consumer"
composer init --no-interaction --name=coisa/production-fixture
composer config repositories.error-handler path "$fixture/package"
composer require 'coisa/error-handler:*@dev' --no-dev --no-plugins --no-scripts --no-interaction
composer check-platform-reqs --no-dev
php "$repository/tests/Production/container.php" "$fixture/consumer/vendor/autoload.php"
composer show --locked
```

The smoke test constructs the core handler, resolves the existing container's
aliases and factories, invokes `ConfigProvider`, and checks both PSR-11 exception
interfaces. It does not install PHP callbacks. `git archive` honors the package's
distribution exclusions, so the smoke test is run from the source checkout.

Repeat in fresh fixture directories on the actual minimum PHP 7.4 runtime and
modern PHP runtimes. For dependency bounds, add an exact `psr/container`
requirement to the consumer, testing `1.1.0`, the latest `1.1`, `2.0.0`, and the
latest `2.0`. Keep at least one consumer requiring only the library to verify
transitive runtime installation. Version `1.0.0` must fail dependency resolution.
Record actual runtime and resolved package versions; a simulated Composer
platform alone does not prove runtime compatibility.
