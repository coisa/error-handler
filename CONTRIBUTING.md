# Contributor dependencies

This library intentionally does not track `composer.lock`. Composer constraints
in `composer.json` describe its supported dependency contract. Applications that
consume the library own their application lockfiles.

Use Composer directly for dependency installation and quality checks.
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

The `coisa/php-cs-fixer:^3.0` preset supplies the formatter policy. A local override
limits multiline trailing commas to arguments, destructuring and arrays;
parameter and `match` commas require PHP 8.0. The preset's automatic PHP migration
target follows the Composer minimum, and its remaining rules are retained.
The opening file header in `.php-cs-fixer.dist.php` is the canonical header
template, read through the preset's `HeaderExtractor::extractFromFile(__FILE__)`.
The preset updates the ending copyright year when formatting.
The superseded PHP-CS-Fixer 2 `.php_cs` configuration is removed. Composer
commands and CI use `.php-cs-fixer.dist.php`; pass that same path when invoking
the formatter explicitly. The old configuration called `Config::create()`,
which the installed PHP-CS-Fixer 3 API does not provide.
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

The [PHP CI workflow](.github/workflows/ci.yml) runs on pull requests,
pushes to `master` and manual dispatches. Each job records the actual PHP,
Composer and resolved package versions. No Composer lockfile or simulated PHP
platform is needed.

| Jobs | Actual PHP versions | Dependency resolution and checks |
| --- | --- | --- |
| Development quality | 8.3, 8.4, 8.5 | Fresh current development graph; formatting, PHPCS, PHPUnit and maximum PHPStan over `src` and `tests` |
| Production | 7.4, 8.0, 8.1, 8.2, 8.3, 8.4, 8.5 | Current runtime graph; tracked PHP syntax, distribution contents, public container and alias factories |
| Production lower bounds | 7.4, 8.5 | Fresh `--prefer-lowest --prefer-stable` runtime graph; the same production checks |
| PHPUnit coverage | 8.5 with Xdebug | Fresh development graph; Clover/Cobertura, baseline comparison and Markdown |

Production jobs install only the library into a separate consumer from its
committed `git archive`, with path-package mirroring instead of a source symlink.
The consumer requires only `coisa/error-handler`, so PSR-11 must be supplied
transitively. Lower-bound jobs exercise the earliest allowed runtime packages;
current-resolution jobs exercise the newest compatible packages available to
each actual PHP version. The recorded graph is the evidence for those bounds,
not a claim that every possible dependency combination was tested.

Composer 2 installations disable plugins and scripts; quality scripts run only
in their explicit check step. Quality jobs explicitly use `error_reporting=E_ALL`
so deprecations can also reach the configured PHP handlers. Jobs have time limits
and no persisted checkout credential. Collection and validation jobs use read-only
repository permissions; the report job requests PR-comment access. The workflow uses the verified
major action refs documented by [Checkout](https://github.com/actions/checkout)
and [Setup PHP](https://github.com/shivammathur/setup-php). The independent changed
PHP workflow retains the shared preset's `3.0.1` reusable workflow ref.

Quality and production jobs disable coverage drivers. A green quality job proves
the checks it ran, without claiming coverage or permission to release.

## PHPUnit coverage and PR notes

The [PHP CI workflow](.github/workflows/ci.yml) has a read-only `coverage` job
on PHP 8.5/Xdebug and a dependent `coverage-report` job. PHPUnit 9.6 produces
Clover plus Cobertura through its native `--coverage-cobertura` flag. The complete
`src/` filter, runtime PHP constraint and public APIs remain unchanged.
`coverage-report-php` retains both XML files for 14 days.

The comparison action receives Cobertura to measure lines. Its
[Clover parser](https://github.com/clearlyip/code-coverage-report-action/blob/v7/src/reports/clover/parser/index.ts)
combines statements, methods and conditionals: the initial 12/254 elements
(4.72%) differ from **9/191 lines (4.71%)**. That initial 2.x measurement used
101 tests and 199 assertions on PHP 8.5.11. A new listener regression test proves
the original throwable is returned even when its callback returns a replacement.
Its specific `@covers` scope raises the suite to **12/191 lines (6.28%)** with
102 tests and 201 assertions. Those are historical starting measurements. Direct
unit tests now measure the individual runtime classes; composition tests retain
`@coversNothing`. Genuine native termination runs as PHPUnit Process tests, with
external children outside the parent's measurement. The retired standalone
Fire/HTTP runners are replaced by direct assertions and the few bounded native
fixtures described in [testing](docs/testing.md). Read each current report's
exact SHA and metric rather than treating those historical counts as current.

The action uses its standard templates and permits coverage decreases against the
target-branch baseline. It fails when reported overall line coverage is below the
repository variable `MINIMUM_COVERAGE_THRESHOLD`, defaulting to **80%** when that
variable is unset. The variable can override the default; this workflow creates
or changes no repository variables. With the default, 80% passes the floor and
79% fails, even if both are decreases. Execution, invalid-report and artifact
failures remain failures independently of this policy.

The floor applies to the complete measured source, not each class or changed file.
Cobertura's overall line rate weights lines across that source; it is not an
arithmetic mean of file percentages. Changed files, their colors and positive or
negative differences remain visible in the report, without individual drop gates.
See the action's [independent threshold and difference inputs](https://github.com/clearlyip/code-coverage-report-action/blob/v7/action.yml).

After successful collection, the report job downloads this run's XML artifact
and generates the job summary and `coverage-pr-comment` Markdown artifact
(14-day retention). Pushes and manual runs on `master` store the Cobertura
baseline as `phpunit-coverage-master` for 90 days. Before that baseline exists,
or after it expires, the report shows current coverage without a comparison.
The minimum still applies without a baseline, but no difference can be displayed.
A manual `PHP CI` run on `master` refreshes it. A manual run on another branch
stores a baseline for that actual branch, allowing a stacked PR targeting it to
compare before the workflow reaches `master`.

The same report job updates one sticky note only for PRs from this repository,
excluding branches starting with `dependabot/`, when its token permits comments.
Fork and Dependabot runs retain useful artifacts but do not receive an automatic
coverage comment through this workflow. There is no privileged fallback workflow.
If a threshold check fails after producing Markdown, the report and eligible PR
note still update while the job remains failed.

Each report identifies the event's head SHA, the tested commit (which can be the
PR merge commit), and the run URL. A failed or cancelled collection skips the
report job and may leave an older note; read its displayed SHA rather than
assuming it certifies the current head. The workflow does not make an atomic
head check when commenting. Authentic master baselines and eligible sticky-note
updates have been observed; each new result still needs its own SHA/run check.

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

## Optional development HTML dependency

The development graph includes `filp/whoops:^2.18.4` to verify the opt-in HTML adapter. Consumer runtime requirements remain unchanged: applications install Whoops only when selecting development HTML, and ordinary no-dev core/container installations need none of it. Record actual lower/current Whoops resolutions and PHP versions when verifying the bridge. The [HTML guide](docs/http.md#opt-in-development-html-with-whoops) describes explicit activation, fresh page configuration and diagnostic disclosure limits.
