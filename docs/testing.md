# Reproducing the failure tests

The suite deliberately causes errors in **child processes**, never in PHPUnit or a host application. Fixtures contain only synthetic data. No external service or credential is used.

## Commands

```sh
# Reference development lock: PHP 8.5 + Composer 2.
composer install --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check

# On PHP 8.3 or 8.4, first resolve compatible development dependencies.
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check

# Core crash tests need PHP only; no Composer dependencies or extensions.
php tests/Fire/run.php

# Include the three isolated PSR renderer regressions after Composer installation.
php tests/Fire/run.php --with-psr

# PSR integration and real HTTP responses.
composer --no-plugins test
php tests/Http/run.php
```

The checks run PSR-12 formatting (with the existing declare-before-license convention), PHP_CodeSniffer, PHPUnit, PHPStan at maximum level over `src/`, crash tests and loopback HTTP smoke tests. A narrow PHPStan stub records that `ob_end_clean()` can throw through a user-defined output callback, as demonstrated by a child regression; no analysis errors are suppressed. Deliberately invalid `.inc` crash fixtures are excluded from lint and autoload classmaps. PHPUnit restores its own error handler between tests; the PHP-warning integration case explicitly disables PHPUnit's error handler so the package receives the real warning.

## Fire-test contract

Every child uses `php -n`, a 16 MiB PHP memory limit, a five-second wall-clock deadline and a 256 KiB combined output cap. The parent captures stdout, stderr, exact exit status, event ordering, event counts, throwable class and applicable severity. Exceeding a limit terminates that child and fails the suite. A native fatal diagnostic on stderr is expected only in the scenarios that explicitly exercise it.

| Cases | Expected observation |
| --- | --- |
| User/native warnings | One `ErrorException`; execution continues, exit 0 |
| `error_reporting()` and `@` | Suppressed warnings are not delivered |
| Uncaught exception, `Error`, `TypeError`, included parse error | One original throwable, exit 1 |
| Uncaught exception with custom/nested shutdown | Only the active custom callback runs; exit 1 |
| Uncaught exception followed by a throwing shutdown callback | Original and shutdown throwables each delivered once; exit 1 |
| Exception callback replaced by the reporter | Replaced instance does not receive shutdown |
| Compile fatal, nested-handler compile fatal | One active-handler delivery, native diagnostic, exit 255 |
| Controlled allocation failure | One fatal delivery, native diagnostic, exit 255 |
| 25 register/unregister cycles and final registration | One shutdown callback |
| Unregistered instance | No shutdown delivery |
| Nested handlers and previous native callbacks | Restoration preserves the original error mask; previous exception handler exits 42 |
| Out-of-order unregister | Rejected without removing the active handler |
| Reporter throws, shutdown reporter throws, recursive reporter | One reporter invocation and a bounded nonzero failure |
| Warning inside the PHP-error reporter | Native diagnostic; no recursive reporter call |
| Custom shutdown throws | Its throwable is delivered once |
| Aggregate | Reporters run in attachment order |
| Renderer failure with removable/non-removable nested buffer (`--with-psr`) | Original renderer failure propagates without a loop; non-removable buffers remain active |
| Renderer and output-cleanup callback both throw (`--with-psr`) | Original renderer failure wins; reachable buffers are cleaned |

The allocation case deliberately requests 32 MiB under a 16 MiB limit. It proves this failure path only. There is no memory reserve and **no guarantee** of reporting arbitrary real-world out-of-memory conditions.

## Observed baseline and verification

On 2026-09-15, the original `f7284cd` source passed 10 of the first 19 isolated scenarios on macOS arm64/PHP 8.5.10. Four uncaught throwable cases exited 0; 25 registration cycles caused 26 shutdown deliveries; failing reporters were called three times; recursion produced 9,389 events before the output cap terminated the child. The original Composer lock could not install on PHP 8.5 because several locked integrations excluded it.

The final 24-case core suite, including nested fatal-handler and custom shutdown coverage, passed on macOS arm64/PHP 8.5.10 and official Linux aarch64 images for PHP 8.3.33, 8.4.25 and 8.5.10. PHP 8.2.33 also passed the original 19 core cases during investigation but is outside the new support contract.

Independent review exposed two regressions in the first modernization draft: custom shutdown was skipped after an uncaught exception, and cleanup looped on a non-removable output buffer. The new child tests failed before the fixes; the non-removable case was terminated by the runner's output cap. An additional regression demonstrated that a throwing output callback could replace the original renderer failure and leave a buffer open. With the fixes, the suite includes 24 core cases and three PSR renderer cases. The latter require Composer dependencies and run automatically through `composer --no-plugins check`.

Native PHP 8.5.10 verification additionally runs PHPUnit 13.3.4, PHPStan 2.2.14 and three real HTTP requests through PHP's built-in server: 500, 503 and TypeError→500, all with a redacted body. The CI matrix checks lowest/highest compatible dependencies on PHP 8.3, highest on 8.4, and locked/highest on 8.5. A green result applies only to the exact checked commit; consult the [Actions run](https://github.com/coisa/error-handler/actions/workflows/ci.yml) rather than assuming that a configured matrix has passed.

Before the service-provider addition, the full `composer --no-plugins check` command passed on native PHP 8.5.10/PHPUnit 13.3.4 and isolated PHP 8.3.33 with both highest dependencies (PHPUnit 12.5.35) and lowest allowed dependencies (PHPUnit 12.5.8, PHPStan 2.1.0, PSR Container 1.1.0): 100 tests and 144 assertions, maximum-level source analysis, formatting, all 27 isolated scenarios and all three HTTP responses. The runnable CLI example was checked for exit status 1, empty stdout and both expected diagnostic messages on stderr.

```sh
docker run --rm --network none --read-only --memory 128m --cpus 1 --pids-limit 32 \
  -v "$PWD:/app:ro" -w /app php:8.3-cli-alpine php tests/Fire/run.php
docker compose run --rm --build tests

# Lowest allowed dependencies, isolated from the host's vendor/ and lock.
docker run --rm --memory 512m --cpus 2 --pids-limit 64 \
  error-handler-modernization-tests sh -c \
  'composer update --prefer-lowest --prefer-stable --no-plugins --no-scripts --no-interaction --prefer-dist --no-progress && composer --no-plugins check'
```

Container builds resolve development packages for the requested PHP version. For a specific matrix run, record the actual PHP version, resolved dependencies and image digest; floating version tags may change. The recorded image digests are:

- PHP 8.3.33: `sha256:afdf8b1fee58486ccc0dab5f30f634b86873d56dac985f71ba217945647c05ad`
- PHP 8.4.25: `sha256:2f389f933c3cc58cc622bd243bb4ecff7e6553e2de4387a239bca640c988be19`
- PHP 8.5.10: `sha256:aa66ee4275e0472bafcd18e35142499fcbda6ac90e641e751418e052e6a0d812`

## Service-provider verification

The provider follow-up passes the full check on macOS arm64/PHP 8.5.10 with
FastForward Container 1.6.0: **118 tests and 196 assertions**, maximum PHPStan,
formatting, the same 27 isolated scenarios and three HTTP responses. Eighteen new
cases cover real-container resolution, reporter overrides, optional discovery,
PSR-14 dispatch, custom PHP/shutdown callbacks, independent factories, nested
middleware and restoration after renderer failure. Missing optional reporters
are skipped only for PSR not-found; separate regressions require other
construction exceptions to propagate.

Independent review reproduced silent reporter fallback when application and
library providers were passed separately to FastForward. Six real-container
regressions now cover required-reporter PSR construction/not-found failures and
ordinary application exceptions for both process handler and fully configured
middleware. Four cases failed with the old composition; all six pass with one
`AggregateServiceProvider`, library first and application last. The skill and
example use this same composition. FastForward may normalize a PSR failure to
not-found, so the contract promises failed construction rather than preservation
of the original exception cause.

`php examples/fast-forward.php` exits 0, emits exactly
`Reported: FastForward provider warning` followed by a newline, and leaves stderr
empty. CI verifies that output. That original provider revision also loaded its
13 definitions without FastForward installed. The review follow-up makes
FastForward a runtime dependency, replaces the standalone container, removes
constructor-only factories and reduces the provider to nine definitions.
Production-only CI now resolves a process handler through FastForward after
`composer install --no-dev`; it no longer asserts FastForward is absent.
The five-job PHP/dependency CI matrix exercises the provider tests too; consult
the current run for commit-specific Linux evidence.

The consumer skill's frontmatter and source references were validated, and its
integration procedure is checked against these same public APIs. Repository
maintenance guidance is in [AGENTS.md](../AGENTS.md); the detailed wiring contract
is in [FastForward integration](fast-forward.md).

## Review follow-up verification

The FastForward-required/autowire revision passes the full check on macOS arm64
with PHP 8.5.11, PHPUnit 13.3.4 and FastForward Container 1.6.0: **89 tests and
185 assertions**, maximum PHPStan over `src/`, formatting, **28** isolated scenarios
and three HTTP responses. The count replaces tests exclusive to the removed
standalone container with retained Laminas integration tests and new autowire,
required-renderer and reporter-reset coverage; it is not the old 118-case suite.

The 25 dependency-free core scenarios also pass on PHP 8.4.26 with `-n`.
An isolated production-only installation resolves the provider/process alias on
PHP 8.5.11 and PHP 8.4.26, without PHPUnit. The existing package versions were
preserved; moving FastForward and its transitive requirements to runtime grows
the locked production set from 8 to 20 packages. Both executable examples retain
their exact output and exit contracts.

The additional reporter-reset child verifies that a failed reporter is not
retried until unregister/register starts a new lifecycle. Six required-renderer
cases cover both HTTP interfaces against PSR construction, PSR not-found and
ordinary application failures. A separate bounded reproduction on FastForward
1.6.0 showed that a concrete renderer override's PSR failure can fall back to
autowiring, while an interface override fails construction. Required custom
renderers therefore use explicit interface bindings in the documented contract.

Highest-dependency CI exposed a version difference: FastForward 1.8.0 normalizes
ordinary application construction failures as well as PSR failures. The tests
therefore require the explicitly failing factory to be reached and construction
to fail with the original throwable or a PSR-11 normalization; they do not promise
original exception identity or a single construction attempt. Handler delivery
after a reporter fails remains a separate no-retry contract.

An isolated highest-dependency installation also passes the full check on PHP
8.5.11 with FastForward 1.8.0, PHPUnit 13.4.2 and PHPStan 2.3.1: 89 tests / 185
assertions, formatting, all 28 child scenarios and the three HTTP responses.
That installation changes only its disposable copy; the repository lock keeps
FastForward 1.6.0 and all existing package versions.

These local results do not establish PHP 8.3 or new Linux matrix results for this
revision. Use the CI run attached to the exact follow-up commit for those.

## Boundaries and troubleshooting

- PHP must have `proc_open()` enabled for crash/HTTP tests. Unit tests require the normal PHPUnit extensions. The HTTP runner binds only to a dynamically selected `127.0.0.1` port, waits at most five seconds to start, gives each request two seconds, and terminates its fixture server in `finally`.
- PHP-FPM, Apache modules, Windows, production load, asynchronous/concurrent runtimes, remote logging failures and process signals are not covered by the local evidence. The Linux CI runner adds its own platform evidence when it succeeds.
- Errors before registration, startup failures and `SIGKILL` cannot be rescued by a PHP shutdown callback. A handler does not make a fatal error recoverable. Native shutdown diagnostics may still be emitted by PHP; applications must configure `display_errors`/logging appropriately.
- A global Composer plugin can interfere with newer tooling. The documented `--no-plugins` commands isolate this project from plugins; they do not modify global configuration.
- If unregistration throws, unwind the middleware/handler stack in reverse order. If a renderer throws, its exception propagates after reachable removable buffers are cleaned. Cleanup stops at a non-removable buffer because PHP cannot remove it or reach lower buffers. Renderers must use removable buffers and must not close buffers owned by the caller.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php), [error-handler semantics](https://www.php.net/manual/en/function.set-error-handler.php), [exception handlers](https://www.php.net/manual/en/function.set-exception-handler.php), [shutdown semantics](https://www.php.net/manual/en/function.register-shutdown-function.php), [output buffer status](https://www.php.net/manual/en/function.ob-get-status.php).
