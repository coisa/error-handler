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
Do not bypass platform requirements. PHP 8.4 is the checked development runner;
the Laminas integration prevents resolving the complete development graph on
PHP 7.4. A production consumer must resolve only the library's runtime
dependencies when checking that minimum.

## Quality checks

The `coisa/php-cs-fixer:^3.0` preset supplies the formatter policy. A local override
limits multiline trailing commas to arguments, destructuring and arrays;
parameter and `match` commas require PHP 8.0. The preset's automatic PHP migration
target follows the Composer minimum, and its remaining rules are retained.
Run each check independently when diagnosing a failure:
`composer check` stops at the first failed command.

```sh
composer --no-plugins cs-check
composer --no-plugins sniff
composer --no-plugins test
composer --no-plugins analyze
```

`test` runs all unit and functional cases in isolated PHP processes without
requesting coverage. `test-coverage` runs the same suite and writes the configured
Clover, HTML and text reports; it requires Xdebug or PCOV. A successful ordinary
test run is not coverage evidence. PHPUnit's configuration targets its 9.x
schema. The explicit `phpspec/prophecy-phpunit:^2.1` bridge supplies
`ProphecyTrait`; PHPUnit's deprecated built-in bridge is not used.

PHPStan remains at maximum level over both `src` and `tests`.
`jangregor/phpstan-prophecy:^1.0` supplies the extension compatible with PHPStan
1.x, loaded from its package-root `extension.neon`. The obsolete callback
suppression was removed so that its diagnostic remains visible.

Restoring these integrations leaves existing style and source/test type findings
visible; they can still fail the aggregate check. The toolchain work is tracked
in [issue #10](https://github.com/coisa/error-handler/issues/10). Do not hide
findings or treat an executable tool as a passing quality gate. Broad style/PHPDoc
cleanup belongs to the separate
[issue #19](https://github.com/coisa/error-handler/issues/19).

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
