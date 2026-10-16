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
coverage job compares the authentic target-branch baseline and permits decreases
while enforcing an overall minimum. The repository variable
`MINIMUM_COVERAGE_THRESHOLD` configures that floor, defaulting to **80%** when unset.
See [coverage reporting](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md#phpunit-coverage-and-pr-notes).

Overall line coverage is covered instrumentable lines divided by all instrumentable
lines, rather than the average of class percentages. Ten uncovered lines in one
class and 90 covered lines elsewhere yield 90% overall. An 80% overall floor does
not require 80% in every class; file colors and negative differences remain visible
without independently failing the job. At the default floor, a decrease to 80%
passes and a result below 80% fails. A different configured floor changes that
boundary. Even 100% overall line coverage does not prove every branch or behavior.

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
| Output callback throws | Primary failure remains; cleanup continues when PHP removed that buffer and stops without progress |
| Output callback exits | Its exit status wins; caller catch is never reached |

Direct tests retain empty/`"0"` diagnostic fallback and successful unbalanced
capture: the inner output becomes the body and an outer buffer remains. Passing
characterization does not endorse these limitations or change runtime policy.

Six instrumentable lines forward a genuinely fatal last error; three stream
cleanup lines stop protected, failed or non-progressing cleanup. These remain
outside ordinary unit measurement. A real throwing native cleanup callback is
tested directly with removable scopes restored in `finally`. Native process
tests prove the other reachable hazardous operations; synthetic substitutions that made a native
function return success without closing a buffer were discarded. No private
reflection, coverage ignores, source exclusions or child-coverage protocol merging
is used to claim 100%. Consult the implementation PR for exact reports and SHAs.

## Consumer examples and documentation

### Direct logging compatibility

The opt-in `LogThrowableHandler` consumes the existing `psr/log`
`^1.1 || ^2.0 || ^3.0` interface without supplying a logger implementation.
Verify each major explicitly: an ordinary highest/lowest dependency matrix can
omit 2.x. Resolve an isolated application on its actual PHP, select the PSR-3
major there, then run the focused `LogThrowableHandlerTest` PHPUnit cases and
[logging recipe](https://github.com/coisa/error-handler/blob/master/examples/logging.php).
PHP 7.4 needs a compatible 1.x resolution; modern fixtures can exercise 2.x/3.x.
Do not bypass platform requirements or change the library's dependency range.
Recording-log tests establish calls, messages, context and failure propagation,
not delivery to a real backend. Consult the implementation PR for the exact
runtimes, resolved versions and measured coverage.

### Cookbook and site

Run `php examples/verify.php` after installing the development/application
dependencies. The [cookbook](https://github.com/coisa/error-handler/blob/master/examples/README.md)
and [verifier guide](https://github.com/coisa/error-handler/blob/master/examples/verify.md)
describe its real inputs, expected outputs and separate bounded native-fatal
example. It uses Symfony Process directly and no test helper framework.

Run `php tests/Documentation/site.php` with PHP 7.4+ and DOM to verify displayed
source excerpts, unique IDs, anchors and links in `docs/index.html`. It also checks
every complete recipe in [the 2.x cookbook](examples.md) against executable source,
omitting only its leading license header. Duplicate, changed or missing recipes
fail validation; new executable recipes need a matching complete entry. The shared
bootstrap, assertion helper and verifier are setup tools rather than recipes.
Browser layout, keyboard navigation and accessibility require separate visual evidence.
The [documentation workflow](https://github.com/coisa/error-handler/blob/master/.github/workflows/pages.yml)
performs these read-only checks on PRs and manual runs. GitHub Pages publishes
the configured branch's `/docs` folder (currently `master`) through its native
branch deployment; the workflow has no upload or deployment job. See GitHub's
[publishing-source documentation](https://docs.github.com/en/pages/getting-started-with-github-pages/configuring-a-publishing-source-for-your-github-pages-site).

Native PHP CI provides style/tests/PHPStan/coverage and clean consumer evidence.
The retired Scrutinizer requested additional data-flow security analysis; PHPStan
and PHPCS are not equivalent to that capability. Repository cleanup does not
alter the external service. FPM/Apache, Windows, concurrency, arbitrary reporter
side effects and production load still require application-specific validation.

### Optional Whoops HTML adapter

`WhoopsThrowableResponseFactoryTest` uses real Whoops pages and PSR messages. Synthetic superglobal tables have positive exposure controls before configured key hiding and are restored after every test. Tests cover status boundaries/string SQLSTATE, original identity, one report, fresh sequential pages/bodies, escaped text and unchanged collaborator failures. The method touching native callback stacks uses PHPUnit's standard separate-process isolation. Caller output/buffers and continuation are tested without mocking native functions. Native CGI headers require separate bounded SAPI evidence, not CLI header assertions.

Whoops is a development dependency for these tests, optional for consumers. Verify the supported `^2.18` lower bound as well as the current version on actual PHP; a declared constraint alone is not execution proof. Production consumers without Whoops must load core/container and the adapter class, then receive a useful `LogicException` only if they select its constructor. Do not fake dependency absence inside the installed development autoloader. Consult the implementation PR for actual graphs and SHAs.
