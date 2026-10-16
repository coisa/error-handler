# Bounded example verification

Run `php examples/verify.php` from the source checkout after installing the application dependencies in [the cookbook](README.md). PHP 7.4+ CLI must provide proc_open; no shell, real server, credentials or production data is needed.

Optional `ERROR_HANDLER_AUTOLOAD` names a readable Composer autoloader from an isolated application. It falls back to the checkout's vendor/autoload.php. Paths and package versions belong to the caller; a Composer platform setting alone does not prove minimum-runtime behavior.

The verifier reuses `tests/Support/process.php` from the source checkout. It uses its own PHP_BINARY and starts one child per fixed scenario with `php -n`, a 64 MiB PHP memory limit, a five-second active deadline and a 16 KiB combined stdout/stderr cap, retaining at most one additional byte to detect overflow. The shared runner reads bounded chunks, checks the final drain and closes pipes/process handles in `finally`; cleanup allows 250 ms graceful termination and one second of forced-termination observation. Every scenario must confirm that its child was reaped. See [the harness guide](../docs/testing.md) for its self-checks and platform limits.

Children receive only an explicit environment containing the fixture-only ERROR_HANDLER_BOUNDED_FIXTURE flag and optional ERROR_HANDLER_AUTOLOAD. The guarded native-fatal case cannot run as an ordinary example. The verifier does not mutate its parent's environment; other environment variables are not forwarded.

Successful execution prints seventeen scenario results and the actual PHP version, then exits 0. This includes explicit filtering of synthetic deprecations while preserving the general reporting branch and original objects. A failed assertion, unavailable process API, unexpected exit/output or bound violation causes a nonzero verifier exit. No host configuration or application callback is changed in the parent. Children use only synthetic input and their own callbacks/buffers.

The registered uncaught fixture currently reports then exits 0; the native compile fatal reports at shutdown and exits 255. These are observed 2.x contracts, not a general PHP process recovery guarantee.

The reporter-failure HTTP case verifies that the library discards its removable capture buffer before propagating failure. It measures buffer delta zero before the fixture's final safety cleanup and checks callback restoration. Protected buffers and arbitrary output-handler side effects require the focused cases and limits in [testing](https://github.com/coisa/error-handler/blob/master/docs/testing.md).

On failure, retain the diagnostic and isolated application's Composer lock/version record. Fix the affected example or documentation against the selected library API, then rerun only affected scenarios before the full bounded verifier. Do not debug a fatal case inside an agent/application process.

Use the same script with actual PHP 7.4 and modern runtimes, resolving compatible application dependencies per runtime. Source lint, style checks, official skill validation and this behavioral check are separate evidence. None authorizes publication or a production change.
