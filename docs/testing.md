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
  Optional Interop provider cases use the real FastForward development dependency:
  inert lookup, cached aliases, fresh nested middleware scopes, legacy defaults,
  JSON/configuration, independent provider responsibilities, JSON-first versus
  base-first selection, lazy shared Whoops pages, aggregate override precedence,
  report ordering and failure identity.
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
  tests of a development checkout cannot establish. This includes construction
  of the optional Interop provider objects without FastForward installed; their
  service maps are only used in the explicit PHP 8.3+ integration.

The old standalone Fire/HTTP runners and custom process helper have been retired.
Ordinary warnings/capture and the four HTTP response tuples now run directly in
PHPUnit. No server is needed: the library returns PSR responses and applications
own emission. Integer 404 stays 404, 503 falls back to 500, ordinary exceptions and
TypeError produce 500; tests also verify exact captured bodies, original events
and restored native callbacks. They do not certify an HTTP server or change the
legacy defaults. The opt-in JSON/provider tests separately assert one private
report and generic public output; they do not imply the proposed major's default
rendering policy.

## Process bounds and known limits

The core lifecycle and output fixtures run with `PHP_BINARY -n`, a 16 MiB memory
limit, a two-second Symfony Process timeout and an interruption threshold of
64 KiB combined output.
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
| Reporter closes capture at native depth zero | Existing diagnostic fallback is returned with no missing-buffer warning |

Direct tests retain empty/`"0"` diagnostic fallback and successful unbalanced
capture: the inner output becomes the body and an outer buffer remains. Passing
characterization does not endorse these limitations or change runtime policy.
Another direct unit regression closes capture before returning and verifies the
exact diagnostic fallback, unchanged caller bytes/depth and no premature caller
callback. Only the depth-zero case runs through the existing bounded child, so
the test does not close PHPUnit's own output buffers.

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

With PHP 8.3+ and `fast-forward/container:^1.9`, run
`php examples/verify.php --with-fast-forward` to include both JSON and both Whoops
provider modes in the same bounded runner, with Whoops installed for HTML. The default remains independent of this optional
runtime. Core production consumers install no library development dependencies;
Quality PHP 8.3–8.5 runs the provider unit tests and maximum PHPStan with the actual
FastForward graph. This adds no new runner or alternate analysis stubs.

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

### Bundled consumer programs

`ConsumerSkillExamplesTest` runs the canonical skill's complete application
programs with an explicit consumer autoloader in bounded children. The optional
Whoops cases include direct HTML and both aggregate-provider page configurations;
their programs require `^2.5` for JSON/Whoops/provider APIs published in 2.5.0 and
reject unavailable symbols rather than treating the common `^2.4` baseline as proof
of every additive class. Installed-package checks run the
same programs with the consumer's real dependencies. The archive guard requires
every canonical file and its source bytes, including focused references, examples
and license; these checks do not install or activate an agent skill.

### Optional Whoops HTML adapter

`WhoopsThrowableResponseFactoryTest` uses real Whoops pages and PSR messages. Synthetic superglobal tables have positive exposure controls before configured key hiding and are restored after every test. Tests cover status boundaries/string SQLSTATE, original identity, one report, fresh sequential pages/bodies, escaped text and unchanged collaborator failures. The method touching native callback stacks uses PHPUnit's standard separate-process isolation. Caller output/buffers and continuation are tested without mocking native functions. Native CGI headers require separate bounded SAPI evidence, not CLI header assertions.

Whoops is a development dependency for these tests, optional for consumers. Verify the supported `^2.18.7` lower bound as well as the current version on actual PHP; a declared constraint alone is not execution proof. Check real Composer resolution rejects 2.18.6, accepts 2.18.7 and still installs without Whoops. Production consumers without Whoops must load core/container and the adapter class, then receive a useful `LogicException` only if they select its constructor. Do not fake dependency absence inside the installed development autoloader. Consult the implementation PR for actual graphs and SHAs.

The optional Whoops provider uses real FastForward in PHPUnit: default/custom page services remain lazy and cached, configuration persists and sequential diagnostic/GET data is replaced. Tests exercise the exact provider map and both separate-provider orders, plus application overrides through one aggregate. With the selected Whoops integration present, rendering reports the original once and reporter/Whoops failures retain identity. Tests distinguish a failing concrete page factory's autowiring fallback from incompatible returned services and actual rendering failures. Native lifecycle cases are isolated and verify distinct active handlers and restoration; pure rendering cases install no callbacks. The bounded provider recipe then exercises the complete middleware/render/report path from a real source file. Installed consumers additionally verify profile construction without Whoops, the missing-dependency hint through the recommended aggregate, the separate-provider fallback limit and an application `--no-dev` graph with FastForward and Whoops explicitly required. Core PHP 7.4 and optional FastForward PHP 8.3+ are separate platform checks.

`ProcessBehaviorTest::testWhoopsNativeSourceFrames` executes the same owned fixture as a real file, native STDIN and `php -r`. The existing Symfony Process limits bound each child to two seconds, 64 MiB and 64 KiB of observed output. Children clear inherited environment values and replace Whoops tables with synthetic data; they emit only a JSON summary, not diagnostic HTML. The assertions preserve original identity and trace/source names, ordered delivery of one legitimate application warning and the original exception, status 503, HTML headers, caller buffers and restored callbacks. Whoops 2.18.7 fixes the virtual-source reads upstream; no suppression, native-function double or synthetic filename establishes the result. Child execution remains separate from parent unit coverage, while the existing renderer unit tests exercise real-file rendering and collaborator failures directly.
