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
Do not bypass platform requirements. CI checks the complete development graph
on PHP 8.3–8.5; the formatter preset requires PHP 8.3, and the Laminas integration
also prevents resolving the complete development graph on PHP 7.4. A production
consumer must resolve only the library's runtime dependencies when checking
that minimum.

## Quality checks

### Makefile entrypoints

Install Composer 2 independently before using the Makefile. It never downloads
Composer or requests elevated privileges. `make` and `make install` run the
`vendor` target; `make vendor` repeats dependency installation even when the
directory already exists. Configure an existing binary with
`make COMPOSER_BIN=/absolute/path/composer install`, or export `COMPOSER_BIN`.
The existing command-line form `make COMPOSER_BIN='php /path/composer.phar' install`
also works. The variable is expanded as a shell command, as before; use a wrapper
at a path without spaces if the underlying command needs shell quoting.
The default install flags disable plugins and scripts and no longer include
Composer's obsolete `--no-suggest` option.

`make clean` and its `uninstall` alias remove the local `vendor` directory.
`make reinstall` completes cleanup before installation, including when Make's
parallel mode is enabled. Test those destructive targets only in a disposable
fixture; do not use an existing working vendor tree as an experiment.

Scrutinizer configuration and badges remain until their useful checks and
coverage have validated replacements. The native baseline is tracked in
[issue #11](https://github.com/coisa/error-handler/issues/11), and coverage in
[issue #14](https://github.com/coisa/error-handler/issues/14). PHPStan is not
evidence that Scrutinizer's separate security analysis has been reproduced.

### Native quality chain

The `coisa/php-cs-fixer:^3.0` preset supplies the formatter policy. A local override
limits multiline trailing commas to arguments, destructuring and arrays;
parameter and `match` commas require PHP 8.0. The preset's automatic PHP migration
target follows the Composer minimum, and its remaining rules are retained.
The opening file header in `.php-cs-fixer.dist.php` is the canonical header
template, read through the preset's `HeaderExtractor::extractFromFile(__FILE__)`.
The preset updates the ending copyright year when formatting.
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
1.x, loaded from its package-root `extension.neon`. A single callback-signature
exception is bounded by `argument.type`, `src/ErrorHandler.php` and an expected
count of one. The process callback intentionally returns `void`/`null`: PHP
delegates to its native handler only when a callback returns strictly `false`,
as documented by [the PHP manual](https://www.php.net/manual/en/function.set-error-handler.php).
Unmatched exceptions remain errors; do not broaden this rule to other callbacks.

Review formatter diffs as code changes. The shared preset can make test data
providers static, add compatible iterable return types, make unbound closures
static, order methods and replace late-static assertion calls in final test
classes. Verify that providers do not use instance state and closures do not
depend on binding. Qualifying native functions also requires checking for local
namespace overrides. Preserve the public runtime API and PHP 7.4 syntax.

Dependency installation in a fresh checkout resolves a local lockfile; subsequent
installs can reuse it. CI must record the actual resolution for its runner, and
cache keys must not require a committed Composer lockfile.

`package-lock.json` remains tracked for the independent Node release tooling.
Node.js is not a runtime dependency of the Composer library.

## Native development environment

Use an installed PHP runtime and Composer 2 for the commands above. The removed
Docker development wrappers used PHP 7.2 and Xdebug 2.6, below the library's
declared PHP 7.4 minimum. They wrapped Composer and PHPUnit; they were not a
supported runtime image for consumers.

For an existing local resolution, install dependencies without enabling plugins
or scripts, then run the native quality chain:

```sh
composer install --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check
```

The native workflows described below do not build a repository Docker image.
Record the actual workflow/SHA and local runtime results; an active workflow
name alone does not prove a runtime matrix was executed.

## Continuous integration

The read-only [PHP CI workflow](.github/workflows/ci.yml) runs on pull requests,
pushes to `master` and manual dispatches. Each job records the actual PHP,
Composer and resolved package versions. No Composer lockfile or simulated PHP
platform is needed.

| Jobs | Actual PHP versions | Dependency resolution and checks |
| --- | --- | --- |
| Development quality | 8.3, 8.4, 8.5 | Fresh current development graph; formatting, PHPCS, PHPUnit and maximum PHPStan over `src` and `tests` |
| Production | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 | Current runtime graph; tracked PHP syntax, distribution contents, public container and alias factories |
| Production lower bounds | 7.4, 8.5 | Fresh `--prefer-lowest --prefer-stable` runtime graph; the same production checks |

Production jobs install only the library into a separate consumer from its
committed `git archive`, with path-package mirroring instead of a source symlink.
The consumer requires only `coisa/error-handler`, so PSR-11 must be supplied
transitively. Lower-bound jobs exercise the earliest allowed runtime packages;
current-resolution jobs exercise the newest compatible packages available to
each actual PHP version. The recorded graph is the evidence for those bounds,
not a claim that every possible dependency combination was tested.

Composer 2 installations disable plugins and scripts; quality scripts run only
in their explicit check step. Quality jobs explicitly use `error_reporting=E_ALL`
so deprecations can also reach the configured PHP handlers. Jobs have time limits, read-only repository
permissions and no persisted checkout credential. The workflow uses the verified
major action refs documented by [Checkout](https://github.com/actions/checkout)
and [Setup PHP](https://github.com/shivammathur/setup-php). The independent changed
PHP workflow retains the shared preset's `3.0.1` reusable workflow ref.

The PHP CI jobs disable coverage drivers. Coverage collection and publication
are separate work; a green quality job proves the checks it ran, without claiming
coverage or permission to release.

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

Before installing an exported package, inspect its committed archive:

```sh
git archive --format=tar --output=/absolute/temporary/package.tar HEAD
php tests/Production/archive.php /absolute/temporary/package.tar
```

This checks archive paths and bytes against the selected source, including every
runtime file. Then run the clean consumer and production container smoke above.
