# Testing and evidence

## Reproduce the current checkout

Use PHP 8.3–8.5 and Composer 2. This library does not commit a Composer lock;
applications resolve and retain their own locks. Development dependencies are
resolved for the PHP version running the command.

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check
python3 tests/Examples/run.py
```

The check runs formatting, PHP_CodeSniffer, PHPUnit, maximum-level source PHPStan,
bounded child-process fire tests and loopback HTTP tests. Examples are also
formatted and checked. The separate Python command verifies all nine executable
recipes, their exact outputs/exit codes, cookbook/site source parity and HTML anchors.

CI resolves lowest and highest allowed dependencies on PHP 8.3, and highest on
PHP 8.4 and 8.5. It verifies a production installation without development
dependencies from the committed Git archive, checking development-file exclusions
and resolving the real provider. Run `python3 tests/Distribution/run.py` after
committing the candidate; it inspects HEAD, not uncommitted edits.
Consult the [actual CI run](https://github.com/coisa/error-handler/actions/workflows/ci.yml)
for the current SHA; a configured matrix is not evidence that it passed.

## Isolated failures

```sh
# Core scenarios need PHP only, without Composer dependencies.
php tests/Fire/run.php
# Include the four PSR output-buffer cases after dependency installation.
php tests/Fire/run.php --with-psr
php tests/Http/run.php
```

Every fire child uses `php -n`, a 16 MiB memory limit, five-second deadline and
256 KiB combined output cap. The parent verifies stdout/stderr, exact exit status,
delivery order/count, throwable class and applicable severity. Exceeding a bound
terminates the child and fails the suite. Native stderr diagnostics are expected
only in explicitly designated cases.

| Cases | Contract exercised |
| --- | --- |
| Reportable/native warnings, masks and suppression | Converted once; recoverable execution continues; excluded severities are ignored |
| Uncaught exception, Error, TypeError and included parse error | Original throwable reported; successful reporting exits 1 |
| Compile fatal, nested fatal and bounded allocation failure | Observable engine fatal reported at shutdown; native termination remains |
| Repeated registration, unregistration and reverse-order restoration | One active shutdown delivery; previous callback identities and masks restored |
| Custom shutdown after uncaught exception, replacement and failure | Only the owning lifecycle receives notification |
| Failing/recursive reporters and re-registration | No repeated delivery within a failed registration; fresh lifecycle resets it |
| Ordered aggregate | Delivery order and same throwable preserved |
| Removable/non-removable buffers and throwing output callbacks | Bounded cleanup; renderer failure retains precedence; cleanup-only failure propagates |
| Fatal inside a different shutdown callback, fresh and reused handler | Native exit 255 with no delivery: PHP aborts the remaining shutdown queue |

The last two shutdown cases characterize a limit, not successful reporting.
Registering a new generation of callbacks does not resume a queue aborted by an
engine fatal. Only existing child fixtures exercise fatal/process termination.

The HTTP runner starts a loopback-only server on a dynamic `127.0.0.1` port,
waits at most five seconds for startup, gives each request two seconds, and
terminates the fixture server in `finally`. It checks redacted 500/503 responses
and a TypeError mapped to 500. It never uses a real application or production logs.

## Provider and lifecycle regressions

Tests use real FastForward Container definitions and constructor autowiring,
including explicit required reporter/renderer interfaces, optional handlers,
PSR-14 listeners, fresh factories and nested middleware beneath a process handler.
Required construction failures must reach the application factory and fail
without silent fallback. FastForward can normalize exceptions and attempt
construction more than once; exception identity and a single attempt are not promised.

Independent unregistered `handleThrowable()` calls remain usable after a reporter
failure. During registration, failed reporters are suppressed. Built-in middleware
propagates that failure and restores callbacks without invoking the response
renderer again. A response-factory failure also
propagates after callback restoration.

The former Laminas behavior cases now exercise the Interop provider. Removed
ConfigProvider-specific configuration tests were retired; reporter, listener,
logging and HTTP behavior remains covered. See [provider contracts](fast-forward.md).

## Observed evidence

Before fixes, original source `f7284cd` passed 10 of the first 19 fire scenarios
on macOS arm64/PHP 8.5.10. Uncaught throwables exited 0, repeated registration
delivered duplicate shutdown notifications, and failed/recursive reporters were
retried. The current suite adds regressions for the demonstrated defects.

The current local PHP 8.5.11/macOS arm64 installation with FastForward 1.6.0 passes
**75 tests / 180 assertions**, maximum source PHPStan, formatting, **31 bounded
scenarios**, three HTTP responses and nine executable examples. Dependency
snapshots used for these local runs are observations, not a committed lock or
a promise about future resolution. The earlier isolated highest-dependency run
with FastForward 1.8.0 also passed; the current HTTP follow-up requires current-head verification. Tests additionally
verify report-before-render ordering, no stdout from a private reporter/pure JSON
renderer, successful response identity and concrete response overrides.

Earlier Linux aarch64 checks exercised PHP 8.3.33, 8.4.25 and 8.5.10 using official
images. The repository Docker/Compose setup is now removed: the version matrix
runs directly in CI and no runtime image is built or distributed. Those older
image experiments are historical evidence, not a supported image interface.

## Formatting preset compatibility

The original `coisa/php-cs-fixer:2.1.0` preset installs with PHP CS Fixer 3.95.25,
but loading it fails because it extends the removed
`PhpCsFixer\RuleSet\AbstractRuleSetDescription`. An isolated installation
reproduced that failure. Its Composer PHP constraint itself allows PHP 8.3+.
The original modernization commit did not record a specific rationale.

The repository currently selects an explicit PSR-12 baseline for source, tests
and examples. This is functional but is not equivalent to the additional CoiSA
rules, risky transformations and header/PHPDoc policy of the old preset.
The [preset modernization PR](https://github.com/coisa/php-cs-fixer/pull/3) now passes
its PHP 8.3–8.5 matrix and reusable changed-files workflow; do not depend on its unreleased branch
from this library. PHPDoc and licensing remain explicit review requirements.

## Coverage and the sticky PR note

The dedicated coverage job runs PHPUnit on PHP 8.5 with coverage enabled,
exports Cobertura and uses the same report/sticky-comment flow as the CLI
reference. It compares the latest available default-branch baseline, checks a
minimum of 85% line coverage and rejects negative differences when a baseline
exists. Owners can override the minimum through `MINIMUM_COVERAGE_THRESHOLD`
or `MINIMUM_COVERAGE`; values must be integers from 0 to 100.

The local PHPUnit snapshot before the HTTP redesign is **87.04% lines
(168/193)**. This excludes the separately executed bounded fatal and loopback
HTTP fixtures; low shutdown-method coverage does not imply those child scenarios
were skipped. It is evidence for this exact PHP/dependency snapshot.

Read-only CI exports Markdown data. A separate default-branch `workflow_run`
publisher checks the current PR SHA and triggering artifact, reads a bounded
regular Markdown file and updates one sticky note with the commit and test-run
link. It does not check out or execute PR code. The publisher handles same-repo,
fork and Dependabot PRs through the same path. Its first live publication requires
the workflow to reach the default branch; this PR does not merge it automatically.

## Release and distribution verification

```sh
npm ci --ignore-scripts
npm run test:release
npm run changeset:status
```

Node is development release tooling only. `package-lock.json` remains committed
to reproduce it. Seventeen deterministic release tests verify provenance, notes,
tag conflicts, idempotency and API failures without creating tags/releases.
A disposable rehearsal verified the initial major changeset produces 3.0.0,
updates both npm manifests and consumes its note; the working branch remains 2.0.0
and unreleased. See [release maintenance](releases.md) and the archive exclusions
in [.gitattributes](../.gitattributes).

## Evidence limits

PHP-FPM, Apache, Windows, concurrent requests/fibers, production load, process
signals and remote delivery need application-specific validation. Startup errors
and SIGKILL cannot be rescued. Reporters own private diagnostics; a shutdown
callback cannot recover fatal execution or guarantee an HTTP response.

Renderers must balance removable buffers and never close caller-owned buffers.
PHP cannot remove a non-removable top buffer or reach those below it. Unregister
handlers in reverse order, preserving native ownership. Tests need `proc_open()`
and the normal PHPUnit PHP extensions.

Sources: [PHP error handlers](https://www.php.net/manual/en/function.set-error-handler.php),
[exception callbacks](https://www.php.net/manual/en/function.set-exception-handler.php),
[shutdown semantics](https://www.php.net/manual/en/function.register-shutdown-function.php),
[buffer status](https://www.php.net/manual/en/function.ob-get-status.php).
