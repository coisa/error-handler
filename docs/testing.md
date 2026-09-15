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

# PSR integration and real HTTP responses.
composer --no-plugins test
php tests/Http/run.php
```

The checks run PSR-12 formatting (with the existing declare-before-license convention), PHP_CodeSniffer, PHPUnit, PHPStan at maximum level over `src/`, crash tests and loopback HTTP smoke tests. Deliberately invalid `.inc` crash fixtures are excluded from lint and autoload classmaps. PHPUnit restores its own error handler between tests; the PHP-warning integration case explicitly disables PHPUnit's error handler so the package receives the real warning.

## Fire-test contract

Every child uses `php -n`, a 16 MiB PHP memory limit, a five-second wall-clock deadline and a 256 KiB combined output cap. The parent captures stdout, stderr, exact exit status, event ordering, event counts, throwable class and applicable severity. Exceeding a limit terminates that child and fails the suite. A native fatal diagnostic on stderr is expected only in the scenarios that explicitly exercise it.

| Cases | Expected observation |
| --- | --- |
| User/native warnings | One `ErrorException`; execution continues, exit 0 |
| `error_reporting()` and `@` | Suppressed warnings are not delivered |
| Uncaught exception, `Error`, `TypeError`, included parse error | One original throwable, exit 1 |
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

The allocation case deliberately requests 32 MiB under a 16 MiB limit. It proves this failure path only. There is no memory reserve and **no guarantee** of reporting arbitrary real-world out-of-memory conditions.

## Observed baseline and verification

On 2026-09-15, the original `f7284cd` source passed 10 of the first 19 isolated scenarios on macOS arm64/PHP 8.5.10. Four uncaught throwable cases exited 0; 25 registration cycles caused 26 shutdown deliveries; failing reporters were called three times; recursion produced 9,389 events before the output cap terminated the child. The original Composer lock could not install on PHP 8.5 because several locked integrations excluded it.

The final 20-case core suite, including nested fatal-handler coverage, passed on macOS arm64/PHP 8.5.10 and official Linux aarch64 images for PHP 8.3.33, 8.4.25 and 8.5.10. PHP 8.2.33 also passed the original 19 core cases during investigation but is outside the new support contract.

Native PHP 8.5.10 verification additionally runs PHPUnit 13.3.4, PHPStan 2.2.14 and three real HTTP requests through PHP's built-in server: 500, 503 and TypeError→500, all with a redacted body. The CI matrix checks lowest/highest compatible dependencies on PHP 8.3, highest on 8.4, and locked/highest on 8.5. A green result applies only to the exact checked commit; consult the [Actions run](https://github.com/coisa/error-handler/actions/workflows/ci.yml) rather than assuming that a configured matrix has passed.

The full `composer --no-plugins check` command also passed in the rebuilt PHP 8.3.33 container with PHPUnit 12.5.35: 100 tests and 144 assertions, maximum-level source analysis, formatting, all 20 crash scenarios and all three HTTP responses. The runnable CLI example was checked for exit status 1, empty stdout and both expected diagnostic messages on stderr.

```sh
docker run --rm --network none --read-only --memory 128m --cpus 1 --pids-limit 32 \
  -v "$PWD:/app:ro" -w /app php:8.3-cli-alpine php tests/Fire/run.php
docker compose run --rm --build tests
```

Container builds resolve development packages for the requested PHP version. For a specific matrix run, record the actual PHP version, resolved dependencies and image digest; floating version tags may change. The recorded image digests are:

- PHP 8.3.33: `sha256:afdf8b1fee58486ccc0dab5f30f634b86873d56dac985f71ba217945647c05ad`
- PHP 8.4.25: `sha256:2f389f933c3cc58cc622bd243bb4ecff7e6553e2de4387a239bca640c988be19`
- PHP 8.5.10: `sha256:aa66ee4275e0472bafcd18e35142499fcbda6ac90e641e751418e052e6a0d812`

## Boundaries and troubleshooting

- PHP must have `proc_open()` enabled for crash/HTTP tests. Unit tests require the normal PHPUnit extensions. The HTTP runner binds only to a dynamically selected `127.0.0.1` port, waits at most five seconds to start, gives each request two seconds, and terminates its fixture server in `finally`.
- PHP-FPM, Apache modules, Windows, production load, asynchronous/concurrent runtimes, remote logging failures and process signals are not covered by the local evidence. The Linux CI runner adds its own platform evidence when it succeeds.
- Errors before registration, startup failures and `SIGKILL` cannot be rescued by a PHP shutdown callback. A handler does not make a fatal error recoverable. Native shutdown diagnostics may still be emitted by PHP; applications must configure `display_errors`/logging appropriately.
- A global Composer plugin can interfere with newer tooling. The documented `--no-plugins` commands isolate this project from plugins; they do not modify global configuration.
- If unregistration throws, unwind the middleware/handler stack in reverse order. If a renderer throws, its exception propagates after library-owned buffers are cleaned. Renderers must not close buffers owned by the caller.

Sources: [PHP supported versions](https://www.php.net/supported-versions.php), [error-handler semantics](https://www.php.net/manual/en/function.set-error-handler.php), [exception handlers](https://www.php.net/manual/en/function.set-exception-handler.php), [shutdown semantics](https://www.php.net/manual/en/function.register-shutdown-function.php).
