# PHPUnit testing and coverage

The library declares PHP `^7.4 || ^8.0`. Development integrations can require a
newer runtime; resolve their actual graph as described in
[contributor setup](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md).

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check
vendor/bin/phpunit --testsuite Unit --no-coverage
vendor/bin/phpunit --testsuite Process --no-coverage
XDEBUG_MODE=coverage composer --no-plugins test-coverage -- --coverage-cobertura tests/cobertura.xml
```

`composer check` runs formatting, PHPCS, all PHPUnit suites and maximum PHPStan
over source and tests. Coverage needs an enabled Xdebug or PCOV driver. When both
are installed, select the intended driver explicitly rather than inferring it
from a successful test run. Raw Cobertura measures source lines; the independent
coverage job compares the authentic target-branch baseline and retains its
existing overall regression gate. See [coverage reporting](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md#phpunit-coverage-and-pr-notes).

## Test responsibilities

- `tests/Unit`: direct class contracts, including collaborator identity, return
  values, errors, ordering, configuration, caching, PHP warning metadata,
  callback restoration and successful/failed output capture. Specific `@covers`
  annotations describe the subject. Doubles control application-owned services;
  actual PSR message values and real PHP callbacks are used where relevant.
- `tests/Functional`: legacy application/container composition. These tests
  retain `@coversNothing`; direct class tests measure their runtime subjects
  independently. Cases installing native callbacks use explicit PHPUnit
  isolation. Pure tests no longer start an unnecessary PHP interpreter each time.
- `tests/Process`: genuine uncaught/shutdown/fatal and hazardous output-handler
  behavior, asserted by PHPUnit through public Symfony Process. External children
  do not contribute to the parent's unit-coverage XML.
- `tests/Fixtures`: only the small native lifecycle/output fixtures and unchanged
  invalid parse/compile inputs. Run them through PHPUnit, never directly in an
  application, agent or parent test process.
- `tests/Production`: committed-archive and clean no-dev consumer checks. They
  prove installed metadata/autoloading across the declared PHP range, which unit
  tests of a development checkout cannot establish.

The old standalone Fire/HTTP runners and custom process helper have been retired.
Ordinary warnings/capture and the four HTTP response tuples now run directly in
PHPUnit. No server is needed: the library returns PSR responses and applications
own emission. Integer 404 stays 404, 503 falls back to 500, ordinary exceptions and
TypeError produce 500; tests also verify exact captured bodies, original events
and restored native callbacks. They do not certify an HTTP server or a future
report-once/redacted-default policy.

## Process bounds and known limits

Each native fixture runs with `PHP_BINARY -n`, a 16 MiB memory limit, a two-second
Symfony Process timeout and an interruption threshold of 64 KiB combined output.
`finally` calls `stop(0)` on the owned child. Output can arrive in a final chunk
during cleanup; this threshold is not a promise of exact byte-level buffer
retention. The output callback raises once so cleanup cannot replace that
exception while draining pipes. Fixtures never spawn descendants or contact
external domains, credentials, application logs or production services.

Symfony Process is a direct development dependency. Its declared lines 5.4/6.4/
7.4/8.0 permit resolution appropriate to PHP 7.4 and modern development runtimes;
record the actual selected versions. Minimum-runtime component probes and native
CI are evidence for the executed platforms, not universal Windows/signal behavior.

| Real native case | Observed 2.x contract or limitation |
| --- | --- |
| Uncaught Exception/Error/TypeError/ParseError | Original type is reported; process exits 0, a known limitation |
| Compile fatal and bounded OOM | Exit 255, native diagnostic and fatal-severity reporting; no recovery |
| Nested fatal handlers | Both report, preserving the known duplicate-delivery limitation |
| Repeated registrations | Three cycles plus final registration retain four shutdown notifications |
| Previous native callbacks | Reverse-order restoration preserves their actual delivery/mask/exit behavior |
| Reporter throws during native delivery | Three deliveries then exit 255, a known reentry limitation |
| Shutdown adapter throws | Its actual shutdown failure is forwarded to the reporter |
| Protected output scopes | Caller bytes survive; a protected nested scope prevents deeper cleanup |
| Output callback throws | Primary failure remains when control returns; buffers can remain |
| Output callback exits | Its exit status wins; caller catch is never reached |

Direct tests retain empty/`"0"` diagnostic fallback and successful unbalanced
capture: the inner output becomes the body and an outer buffer remains. Passing
characterization does not endorse these limitations or change runtime policy.

Six instrumentable lines forward a genuinely fatal last error; four stream
cleanup lines handle protected/failed/non-progressing/throwing output cleanup.
These are outside ordinary unit measurement. Native process tests prove the
reachable hazardous operations; synthetic substitutions that made a native
function return success without closing a buffer were discarded. No private
reflection, coverage ignores, source exclusions or child-coverage protocol merging
is used to claim 100%. Consult the implementation PR for exact reports and SHAs.

## Consumer examples and documentation

Run `php examples/verify.php` after installing the development/application
dependencies. The [cookbook](https://github.com/coisa/error-handler/blob/master/examples/README.md)
and [verifier guide](https://github.com/coisa/error-handler/blob/master/examples/verify.md)
describe its real inputs, expected outputs and separate bounded native-fatal
example. It uses Symfony Process directly and no test helper framework.

Run `php tests/Documentation/site.php` with PHP 7.4+ and DOM to verify displayed
source excerpts, unique IDs, anchors and links in `docs/index.html`. Browser
layout, keyboard navigation and accessibility require separate visual evidence.
The [Pages workflow](https://github.com/coisa/error-handler/blob/master/.github/workflows/pages.yml)
validates on PRs; artifact upload/deployment require a separately authorized
manual run on the default branch and owner-configured hosting. Merging code does
not publish the site.

Native PHP CI provides style/tests/PHPStan/coverage and clean consumer evidence.
The retired Scrutinizer requested additional data-flow security analysis; PHPStan
and PHPCS are not equivalent to that capability. Repository cleanup does not
alter the external service. FPM/Apache, Windows, concurrency, arbitrary reporter
side effects and production load still require application-specific validation.
