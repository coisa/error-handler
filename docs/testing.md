# Testing the unreleased major

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

## Direct and process tests

The Unit suite exercises handlers, listeners, providers, HTTP adapters and native
callback ownership directly. Functional tests compose the real FastForward
provider, explicit application overrides, cached services and fresh factories.
Only tests that mutate global callbacks request PHPUnit process isolation.

The Process suite uses the public Symfony Process API for actual termination.
Children run `php -n` with 16 MiB, a two-second timeout and an abort after more
than 64 KiB of observed output. A one-shot limit prevents the output callback
from throwing again during cleanup; `finally` stops/reaps the child. A final
pipe chunk can arrive during stop, so the observed limit is not an exact retained
byte ceiling. Fixtures use synthetic data, no network, no credentials and no
application logs. They do not merge child coverage into PHPUnit's parent report.

| Behavior | Evidence |
| --- | --- |
| Warnings, masking and suppression | Direct native warnings, severity/identity metadata and continuation |
| Uncaught Exception, Error, TypeError and ParseError | Bounded child reports original type and exits 1 |
| Compile/allocation fatals, nested handler | Native failure remains fatal; only the active owner reports |
| Re-registration and callback restoration | One retained shutdown callback, stable closure identities and reverse-order ownership |
| Failed or recursive reporter | Propagation without retry during registration; direct reuse and lifecycle reset tested separately |
| Generic HTTP rendering | One diagnostic report, then a separate renderer; status 400–599 and standard reason phrases |
| Output capture | Exact nonempty bytes including `0`, generic empty fallback, cleanup on success/failure, primary failure precedence |
| Protected/terminating output handlers | Externally bounded Process cases preserve caller ownership and show native termination limits |
| Fatal in an earlier shutdown callback | Fresh and reused handlers both receive no event; PHP aborts the remaining queue |

The standalone Fire/HTTP parents and Python verification scripts were retired in
master and are not restored by this major. Their meaningful behavior is checked
through these PHPUnit suites and the bounded example verifier. Synthetic builtin
overrides and self-tests of the retired runner are not equivalent native evidence.

## Production and documentation

CI exports the committed Git archive, verifies development-file exclusions and
installs it into a fresh consumer with `--no-dev --no-plugins --no-scripts`.
Production jobs run PHP 8.3–8.5; lower-bound runtime graphs run on 8.3 and 8.5.
The consumer requires only this library, so FastForward/Interop/PSR dependencies
must resolve transitively. See [production checks](../tests/Production/README.md).
The archive gate also requires every file of the canonical consumer skill,
including nested examples and its license, with exact source bytes. Functional
PHPUnit cases reject missing, changed and unrelated resource assets; the bundled
application programs use an explicit consumer autoloader. See the
[skill examples](../resources/skills/coisa-error-handler/examples/README.md).
FastForward may normalize construction failures and retry autowiring: failed
required construction is asserted without promising one attempt or original cause.

`php examples/verify.php` checks every complete recipe and documented mode,
including the adapters and JSON/Whoops profiles delivered in 2.x. The existing Symfony Process verifier uses
five-second/64 MiB children and aborts above 16 KiB of observed output. It clears
inherited environment variables except the selected autoloader/fixture marker.
`php tests/Documentation/site.php` checks local source links, anchors and all
seventeen complete cookbook recipes against executable source, omitting only the
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
No runtime exclusions, merged child coverage or artificial coverage ignores are
introduced by this reconciliation. PHPStan's output-buffer stub supplies analysis
metadata only and is never loaded by the runtime/autoloader/test suite.

The reconciled local graph uses PHP 8.5.11, PHPUnit 13.4.2, FastForward 1.8.0
and Whoops 2.18.6 on macOS arm64. Coverage is measured separately with PHP 8.5.11 and Xdebug 3.5.3.
These observations do not prove PHP 8.3 or every permitted dependency combination.
The declared FastForward `^1.6` floor includes an older graph with PHP-DI 7.0
that emitted implicit-nullability deprecations under PHP 8.5/E_ALL despite exit 0.
That earlier observation is a dependency diagnostic limit, not a clean result for
the reconciled candidate. Keep its evidence separate from current-graph checks.
The PR evidence records test totals, native uncovered lines and hosted SHAs.
Earlier PR #3 results using 75 tests, custom runners or PSR-12-only formatting are
historical; the current checkout uses the merged CoiSA 3.x preset and PHPUnit suites.

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

Release maintenance uses the existing [Changesets procedure](releases.md).
Preparing or testing this draft does not merge, publish a release or deploy Pages.
