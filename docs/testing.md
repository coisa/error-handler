# Testing and 2.x characterization

The library declares PHP `^7.4 || ^8.0`. Resolve development dependencies for the
runtime used to execute them; see [contributor setup](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md). Existing
quality-toolchain work is tracked in [issue #10](https://github.com/coisa/error-handler/issues/10).

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check

php tests/Fire/run.php
php tests/Fire/run.php --with-psr
php tests/Fire/buffers.php
php tests/Http/run.php
php tests/Http/run.php --check-port-retry
```

For runnable consumer integrations, use the [2.x cookbook](https://github.com/coisa/error-handler/blob/master/examples/README.md)
and its [bounded example verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md).
That verifier reuses the shared process runner below with explicit application
dependencies. Run the commands directly with PHP CLI.

The first fire command requires PHP only and runs 16 core scenarios. `--with-psr`
adds five legacy response-capture scenarios and requires a compatible Composer
autoload file containing the PSR interfaces and Nyholm PSR-7. The HTTP runner uses
the same dependencies. The focused buffer regressions use those dependencies too
and run in the PHP 8.3–8.5 quality jobs after the unchanged Composer checks.

Run these entrypoints directly with PHP CLI and argument collection enabled.
They fail explicitly before starting child fixtures if `$argv` is unavailable;
missing arguments must not silently disable requested options.

For a runtime whose development integrations cannot be installed, create an
isolated fixture directory with only `nyholm/psr7:^1.8` and
`psr/http-server-middleware:^1.0`, resolving with that PHP runtime and Composer
plugins/scripts disabled. Supply its absolute autoload path to both runners:

```sh
php tests/Fire/run.php --with-psr --autoload=/tmp/error-handler-psr-fixture/vendor/autoload.php
php tests/Fire/buffers.php --autoload=/tmp/error-handler-psr-fixture/vendor/autoload.php
php tests/Http/run.php --autoload=/tmp/error-handler-psr-fixture/vendor/autoload.php
```

Use an owned test fixture, never a production application's bootstrap or data.
The source autoloader takes precedence over fixture dependencies, so both runners
exercise this checkout's `src` tree. Composer fixture files belong to their
temporary directory and can be removed after the run.

## Bounds and cleanup

Each fire child runs with `php -n`, a 16 MiB memory limit, a five-second deadline
and a 256 KiB combined stdout/stderr cap. The shared runner reads in bounded
chunks, terminates overdue/noisy children, closes pipes and reaps process handles
in `finally`. Cleanup allows a 250 ms graceful termination period followed by a
one-second forced-termination observation period. The child fixtures do not spawn
descendants. The parent never deliberately exhausts its own memory.

The five harness self-checks execute normal exit, a sleeping child that exceeds
its deadline, a noisy child that exceeds its output cap, and both entrypoints
with missing CLI arguments. They assert exact normal output/exit, termination
reasons, bounded captured bytes, observed reaping and an explicit argument error
before any fixture work.
It runs before the fire cases, or independently:

```sh
php tests/Support/check.php
```

The HTTP runner reserves a dynamic `127.0.0.1` port and starts an owned PHP fixture
server. The reservation must be released before `php -S` binds; if that child
exits before confirming startup, the runner reaps it and tries a fresh port, up
to three attempts. Requests begin only after the owned child's startup banner
and must return a random per-run ownership header. `--check-port-retry` holds the
first reservation deliberately and verifies exactly one rejected bind, reaping
and retry before the four routes pass; no request is sent to the reserved port.
Startup is limited to five seconds per attempt, each request to two seconds and
64 KiB of body, and each active server lifetime to 20 seconds. Each request
runs in its own bounded child, so a streaming response cannot bypass its deadline
by repeatedly resetting an idle timeout. Redirects
are disabled. Server output is capped at 256 KiB and its memory at 32 MiB. The
same `finally` cleanup runs on success and on assertion/request failures. Request
children use 16 MiB of memory and a 128 KiB captured-output cap. No real
server, application logs, credentials or external domains are used.

## What passing characterization means

The 15 focused buffer cases each use a two-second, 16 MiB child with a 64 KiB
combined-output cap. They verify exact primary identity, untouched caller output
and levels, removable nested cleanup, protected buffers, callbacks that throw
during rendering or cleanup, and unchanged successful capture/fallback bytes.
Two child-only namespace function substitutions return false or true without
closing a buffer, proving that a failed close or absent progress stops after one
attempt. Native output handlers cannot safely start buffers while closing one;
the substitutions exercise that defensive boundary without invoking a fatal
callback. They add no production hook. `--source=/absolute/path/src --json`
supports comparison with an archived baseline; the recorded reflected filename
confirms which source was loaded.

The assertions preserve observed 2.x behavior. A passing limitation case records
a defect or boundary; it does not establish successful recovery or fix it.

The non-removable-buffer scenario uses a typed callback that returns captured
bytes unchanged, with cleanable/flushable flags and no removable flag. Its
assertions measure failure propagation and remaining buffer levels, not every
output-handler phase or callback implementation. Other buffer callbacks require
their own focused regression cases.

Process-terminating callbacks are a separate limit: cleanup invokes native output
handlers, so an `exit()`/`die()` inside one can end the process before the primary
failure reaches the caller. Bounded probes on PHP 7.4/8.4/8.5 confirmed the chosen
callback exit status and no caller catch; PHP 7.4 also emitted a native diagnostic.
These observations do not promise particular shutdown output across PHP versions
or recovery from terminating/engine-fatal callbacks.

| Scenarios | Observed contract or known limitation |
| --- | --- |
| User/native warnings and suppression | Reported severities produce one event and execution continues; masks and `@` suppression are honored |
| Uncaught exception, Error, TypeError and included parse error | Original throwable class is reported, but the process exits **0**: known limitation |
| Compile fatal and bounded allocation failure | Native exit **255**, native stderr and fatal severity reporting; execution is not recovered |
| Nested fatal handlers | Both registered handlers report the fatal error: known duplicate-delivery limitation |
| Repeated registration lifecycles | Three unregister/re-register cycles plus the final registration produce **four** shutdown notifications: known limitation |
| Unregistered handler and reverse-order restoration | Unregistered shutdown callback is inactive; previous native callback/mask behavior is restored |
| Throwing reporter | Reporter runs three times across exception/shutdown failure paths, then exits 255: known retry limitation |
| Throwing custom shutdown and aggregate | Shutdown failure is forwarded; aggregate order and original throwable identity are preserved |
| Legacy captured output | Nonempty renderer output is used as the response body |
| Empty output and output `0` | Both fall back to the throwable diagnostic string: known legacy behavior; preserving `0` is separate work |
| Throwing renderer | The same failure propagates after removable capture buffers are discarded; a non-removable nested buffer still leaves two |
| Throwing cleanup callback | The primary renderer failure survives; cleanup stops and can leave buffers behind |
| Successful unbalanced nested capture | The innermost buffer becomes the body and one buffer remains: preserved legacy behavior |

The four HTTP routes verify the actual middleware/response composition: integer
404 remains 404, exception code 503 falls back to 500, ordinary exceptions and
TypeError produce 500. Bodies contain the synthetic renderer's diagnostic text;
this is **not** a redacted generic-body default. Each route also observes one
renderer invocation and restoration of the previous error/exception callbacks.
It does not promise a future report-once middleware policy.

The intentionally invalid `.inc` files belong only to bounded parse/compile
fixtures and are excluded from ordinary syntax/style assertions with local
PHPCS directives. Do not execute them in the parent test process.

## Evidence and limits

The core characterization began as tests of published 2.0.0 behavior. Focused
regressions now also cover the compatible exceptional buffer cleanup above;
successful bytes and the empty/`0` diagnostic fallback remain unchanged.
Local macOS arm64 executions exercised PHP 7.4.33, 8.4.26 and 8.5.11, including the
21 fire scenarios, five harness self-checks and four HTTP routes. Those runtime
observations do not certify other PHP minors, dependency resolutions or CI.
Consult the implementation PR for its exact candidate SHA and executed results.

PHP-FPM, Apache, Windows, concurrent requests/fibers, production load, signals,
process descendants and external reporters require separate application-specific
evidence. Startup errors, SIGKILL and an engine-aborted shutdown queue cannot be
rescued by this harness. These checks do not establish safe behavior for arbitrary
output-handler side effects or change exit, lifecycle or HTTP-default policy.
