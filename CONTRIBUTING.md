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
| PHPUnit coverage | 8.5 with Xdebug | Fresh development graph; Clover, baseline comparison, Markdown and publisher fixture tests |

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

Quality and production jobs disable coverage drivers. A green quality job proves
the checks it ran, without claiming coverage or permission to release.

## PHPUnit coverage and PR notes

The separate coverage job keeps PHP 8.5, Xdebug, PHPUnit 9.6 and the complete
`src/` coverage filter stable. It records the actual resolved tool versions and
uploads `coverage-report-php` (Clover XML) and `coverage-pr-comment` (Markdown),
retained for 14 days. No Composer runtime constraint or public API changes.

The measured 2.x baseline at `27ff64ec6395646d630b3d451f1fdd48f1515305`
is **4.71% of lines (9/191)** with 101 tests and 199 assertions on PHP 8.5.11;
both Xdebug 3.5.3 and PCOV 1.0.12 produced that result. Four test classes explicitly
declare `@coversNothing` and do not contribute to instrumentation. This low
measurement does not imply those behaviors are untested. These annotations stay
intact. Coverage percentages and differences are **informational**: no numerical
floor or regression gate is enforced. A future policy requires a separate review
of the intended instrumentation scope and measured baseline. Neither the
next-major branch's percentage nor its threshold is used.

The report action stores `phpunit-coverage-master` on a push or manual dispatch
of `PHP CI` on `master`; baseline retention is 90 days. PRs compare with the
available target-branch artifact. Before the first baseline exists, or after it
expires, the report says that comparison is unavailable. A manual `PHP CI` run
on `master` refreshes an expired baseline.
No periodic run or external coverage service is required.

When Clover exists, raw coverage is exported even if tests fail. Markdown is
exported when report generation produces it. Execution, invalid-report and
artifact errors still fail the job; the measured percentage itself does not.
A failure before coverage is written has no report artifact. The report covers
only instrumented PHPUnit execution;
process-termination and HTTP child fixtures remain separate behavioral evidence.

The [PR note publisher](.github/workflows/coverage-comment.yml) runs from the
default branch after `PHP CI`, with no checkout and no execution of PR code.
Same-repository, fork and Dependabot PRs all follow this data-only path. It
validates the workflow path, run and attempt, artifact repository/SHA identity,
archive size, and a nonempty regular UTF-8 Markdown file of at most 60,000 bytes.
It then rechecks the open PR's current head and adds the tested SHA, run link and
conclusion itself before updating one sticky note.

Publisher runs are serialized by source repository and branch, with pending
runs queued rather than replaced. An older publisher therefore cannot pause
after its head check and overwrite a concurrently published newer note. The
head check and comment request are still separate operations: a head can move
between them, so the displayed SHA is always the authority for the note.

Stale or closed PRs, superseded attempts and missing reports are logged and
skipped. Ambiguous, expired, mismatched or malformed reports fail the publisher
without replacing the note. Any retained note identifies its tested SHA and
must not be read as proof for a newer head. Reports from failed runs carry the
failure conclusion, and cancelled runs do not publish.

Run the isolated publisher checks locally with:

```sh
node --test tests/Coverage/publisher.test.mjs
```

These execute the actual inline scripts with minimal API-shaped fixtures and
real temporary files, including stale/closed/missing/failure and invalid-data
cases. They do not send comments. The first default-branch baseline and live
sticky creation/update still require observing actual hosted runs after this
workflow is merged; local fixtures and pre-merge artifacts do not prove activation.

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
