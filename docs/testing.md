# Testing

The Composer runtime constraint is `^8.3`. CI exercises PHP 8.3, 8.4 and 8.5;
that test set does not impose an upper cap on the declared PHP 8.x range.
Use Composer 2 and resolve development tools on the actual PHP being tested:

```sh
composer update --no-plugins --no-scripts --no-interaction --prefer-dist
composer validate --strict --no-check-publish
composer check-platform-reqs
composer --no-plugins check
php examples/verify.php
php tests/Documentation/site.php
```

The library does not track a Composer lock. Applications retain their own locks.
The check runs the CoiSA formatter, PHPCS, PHPUnit and maximum PHPStan over
source and tests. The PHPUnit 12.5/13.x graph uses native attributes, not the
retired PHPUnit 9 annotation metadata. Dependency resolution is version-specific;
record PHP, Composer and `composer show --locked` rather than assuming all runners
resolve the same packages. No host runtime installation is required by this guide.

## Symfony compatibility combinations

The workflow's dedicated Symfony matrix selects these component lines and runs
the complete Composer quality check on each resolved graph:

| PHP | ErrorHandler / Serializer | HttpFoundation |
| --- | --- | --- |
| 8.3 | 5.4 / 5.4 | 6.4 |
| 8.3 | 6.4 / 6.4 | 6.4 |
| 8.4 | 7.0 / 7.0 | 7.4 |
| 8.5 | 8.0 / 8.0 | 8.1 |

ErrorHandler and Serializer admit compatible major versions 5, 6, 7 and 8;
HttpFoundation admits 6, 7 and 8. Composer's declared patch floors remain
authoritative, including the HttpFoundation Accept-parser security fixes.
Matrix selectors intersect those constraints; they do not permit an older
vulnerable patch. Record the actual locked component versions and native result
for each run. Selected combinations do not establish every possible solver graph.

## Direct and process tests

The Unit suite exercises handlers, listeners, providers, HTTP adapters and native
callback ownership directly. Functional tests compose the real FastForward
provider, explicit application overrides, cached services and fresh factories.
Only tests that mutate global callbacks request PHPUnit process isolation.

The Process suite uses the public Symfony Process API for actual termination.
Core lifecycle and output children run `php -n` with 16 MiB, a two-second timeout and an abort after more
than 64 KiB of observed output. A one-shot limit prevents the output callback
from throwing again during cleanup; `finally` stops/reaps the child. A final
pipe chunk can arrive during stop, so the observed limit is not an exact retained
byte ceiling. Fixtures use synthetic data, no network, no credentials and no
application logs. They do not merge child coverage into PHPUnit's parent report.

| Behavior | Evidence |
| --- | --- |
| Warnings, masking and suppression | Direct native warnings, severity/identity metadata and continuation |
| Uncaught Exception, Error, TypeError and ParseError | Bounded child reports original type and exits 1 |
| Compile/allocation fatals, nested handler | Native failure remains fatal; only the active owner reports; compile cases preserve message/file/line against the native snapshot and fixture source |
| Re-registration and callback restoration | One retained shutdown callback, stable closure identities, reverse-order ownership and rejection of either callback's isolated replacement without altering native masks |
| Failed or recursive reporter | Propagation without retry during registration; direct reuse and lifecycle reset tested separately |
| Default fail-fast aggregate | The identical reporter failure stops later slots; a subsequent independent invocation can complete |
| Generic HTTP rendering | One diagnostic report, then a separate renderer; status 400–599 and standard reason phrases |
| Output capture | Exact nonempty bytes including `0`, generic fallback for empty/closed capture without reading caller output, cleanup on success/failure, primary failure precedence |
| Capture closed at native depth zero | Generic fallback with no missing-buffer warning, through the existing bounded child rather than closing PHPUnit-owned buffers |
| Protected/terminating output handlers | Externally bounded Process cases preserve caller ownership and show native termination limits |
| Fatal in an earlier shutdown callback | Fresh and reused handlers both receive no event; PHP aborts the remaining queue |

`ProcessBehaviorTest::testWhoopsNativeSourceFrames` executes the same owned
fixture as a physical file, native STDIN and `php -r`. These children use the
normal PHP configuration with a two-second timeout, 64 MiB memory limit and
64 KiB observed-output limit. They clear inherited environment values and replace
Whoops tables with synthetic data, emitting only a JSON invariant summary rather
than diagnostic HTML. The actual major middleware reports once before invoking
the pure renderer; no reporting decorator is added. Assertions retain the
original throwable, trace and native source names, ordered delivery of a real
application warning and the original failure, status 503, HTML headers, caller
buffers and restored callbacks. Whoops 2.18.7 fixes virtual-source reads upstream;
no suppression, native-function double or fabricated path establishes this result.
Child execution remains separate from parent unit coverage. Existing renderer
unit tests exercise real-file rendering and collaborator failures directly.

The terminal handler's native-stderr case uses a two-second, 64 MiB child with
a 64 KiB observed-output bound and cleared inherited environment. It blocks Console
autoloading, reports two synthetic throwables to stderr, verifies that stdout contains only the continuation
marker and confirms the caller continues. Direct tests separately exercise raw
Console/error-output routing and collaborator failures. Child execution is
behavioral evidence; its lines are not merged into the parent's Xdebug/PCOV
coverage. No coverage driver is installed or enabled by that fixture.

Whoops remains optional. Composer consumer resolution must reject 2.18.6, accept
2.18.7 and still install without Whoops. A declared development dependency alone
does not enforce or prove that optional consumer floor.

The PHPUnit suites and bounded example verifier cover the meaningful behavior of
the retired standalone Fire/HTTP parents and Python verification scripts.
Synthetic builtin overrides and self-tests of the retired runner are not
equivalent native evidence.

## Production and documentation

CI exports the committed Git archive, verifies development-file exclusions and
installs it into a fresh consumer with `--no-dev --no-plugins --no-scripts`.
Production jobs run PHP 8.3–8.5; lower-bound runtime graphs run on 8.3 and 8.5.
The consumer requires only this library, so FastForward/Interop/PSR dependencies
must resolve transitively. See [production checks](https://github.com/coisa/error-handler/blob/master/tests/Production/README.md).
The archive gate also requires every file of the canonical consumer skill,
including nested examples and its license, with exact source bytes. Functional
PHPUnit cases reject missing, changed and unrelated resource assets; the bundled
application programs use an explicit consumer autoloader. See the
[skill examples](../resources/skills/coisa-error-handler/examples/README.md).
The standalone production alias-factory fixture enables E_ALL and converts runtime
diagnostics, including deprecations, into failures before loading the consumer
autoloader. A no-dev graph that resolves successfully must still execute that
fixture cleanly; dependency installation alone is not execution evidence.

FastForward may normalize construction failures and retry autowiring: failed
required construction is asserted without promising one attempt or original cause.

`php examples/verify.php` checks every complete recipe and documented mode,
including retained 2.x adapters and the single provider's request negotiation,
Symfony HTML, JSON and explicit optional Whoops composition. The existing Symfony Process verifier uses
five-second/64 MiB children and aborts above 16 KiB of observed output. It clears
inherited environment variables except the selected autoloader/fixture marker.
`php tests/Documentation/site.php` checks local source links, anchors and all
nineteen complete cookbook recipes against executable source, omitting only the
leading license header. Duplicate, changed or missing recipes fail; a new
executable recipe needs its own complete entry. Bootstrap, assertion helper and
verifier are setup tools, not recipes. The site retains its source-parity checks.
Neither command starts a server or publishes Pages.

## Coverage and current evidence

The PHP 8.5/Xdebug job exports native Clover and Cobertura for all `src/` files.
The inherited master policy permits overall decreases and fails below the
repository variable `MINIMUM_COVERAGE_THRESHOLD`, defaulting to **80%** when unset.
The workflow emits per-file differences/colors and a sticky report. Verify which
target or stack baseline the actual run selected before interpreting the delta.
At the default floor, 80% passes and a result below it
fails; a configured variable overrides that default. No variable is changed by
the workflow. Execution, invalid-report and artifact failures remain failures.

Overall coverage weights instrumentable lines, not class percentages: ten
uncovered lines plus 90 covered lines elsewhere produce 90% overall. An 80%
overall floor is not an 80% floor per class. Even 100% of lines does not prove all
branches or behaviors.
The sticky note identifies the head SHA, tested merge SHA and workflow run.
Read those exact identities; a historical note or a running review is not approval.
Runtime coverage includes all source files without merged child coverage or
artificial coverage ignores. PHPStan's output-buffer stub supplies analysis
metadata only and is never loaded by the runtime/autoloader/test suite.

Resolve development dependencies on the actual PHP runtime. PHP 8.3 uses the
admitted PHPUnit `^12.5` line; PHPUnit `^13.4` requires PHP 8.4.1 or later.
Symfony Process similarly admits `^7.4` for PHP 8.3 and `^8.1` for PHP 8.4.1+.
The manifest retains both compatible lines without raising the library's PHP floor.
Native coverage requires the recorded Xdebug or PCOV version; a lockfile or
successful resolution alone does not prove execution on another PHP version.

FastForward Container `^1.10` requires Config `^1.6`, removing PHP-DI and
DotAccessData from that runtime graph. The Nyholm development minimum is 1.8.2,
which includes its PHP 8.5 nullable-parameter fixes. Applications select their
own PSR-17 implementation. Keep dependency diagnostics under `E_ALL` visible and
record the graph, test totals, native uncovered lines and hosted SHA in PR evidence.

## FastForward event-dispatcher integration limits

The event recipe and functional composition use published
[FastForward EventDispatcher 1.0.0](https://github.com/php-fast-forward/event-dispatcher/tree/v1.0.0),
configured with Error Handler's own typed listener-provider objects. They verify
raw Throwable identity, the separate CoiSA ErrorEvent and one private log record
after delivery. This is the tested integration path, not certification of every
upstream dispatcher feature.

Bounded checks of that specific upstream release also found that its subscriber
adapter consumes registrations after the first dispatch; a listener throwing a
PDOException with a string SQLSTATE code can have that failure replaced by
TypeError. Explicitly dispatching its own NamedEvent returns the inner object,
and explicitly dispatching its own ErrorEvent can replace the current listener
failure with the wrapper's earlier throwable. Those wrapper cases concern
FastForward's classes, not CoiSA's ErrorEvent. The library does not patch these
upstream behaviors. Applications may supply another PSR-14 dispatcher; do not
infer a guarantee for failing transports or subscriber/wrapper paths from the
recipe's successful in-memory delivery.

## Limits

PHP-FPM, Apache, Windows, concurrent fibers/requests, signals, remote transports and
production load need application-specific tests. Startup failures, SIGKILL and
fatal errors that abort a shutdown queue cannot be recovered. Output callbacks
can terminate PHP or have arbitrary side effects; cleanup only removes reachable
removable scopes and stops without progress. Reporters own private diagnostics;
a safe PSR response body cannot prevent an application reporter from echoing.

An output-handler object's destructor can open a replacement buffer after PHP
removes its original scope. The direct stream-factory unit test verifies cleanup
stops when depth does not decrease, preserves the original failure and caller
bytes, and never creates a stream. The test explicitly removes its replacement
scopes and restores the fixture in finally; the factory does not promise to
remove every scope after native callbacks or destructors change the stack.

Release maintenance uses the existing [Fast Forward Changelog procedure](releases.md).
