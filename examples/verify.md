# Bounded example verification

Run `php examples/verify.php` from the source checkout after installing the application dependencies in [the cookbook](README.md). PHP 7.4+ CLI must provide proc_open; no shell, real server, credentials or production data is needed.

Optional `ERROR_HANDLER_AUTOLOAD` names a readable Composer autoloader from an isolated application. It falls back to the checkout's vendor/autoload.php. Paths and package versions belong to the caller; a Composer platform setting alone does not prove minimum-runtime behavior.

The verifier uses its own PHP_BINARY and starts one child per fixed scenario, with captured stdout/stderr, a five-second deadline and a 16 KiB output bound. It temporarily sets the fixture-only ERROR_HANDLER_BOUNDED_FIXTURE flag so the guarded native-fatal case cannot be invoked accidentally as an ordinary example.

Successful execution prints sixteen scenario results and the actual PHP version, then exits 0. A failed assertion, unavailable process API, unexpected exit/output or bound violation causes a nonzero verifier exit. No host configuration or application callback is changed in the parent. Children use only synthetic input and their own callbacks/buffers.

The registered uncaught fixture currently reports then exits 0; the native compile fatal reports at shutdown and exits 255. These are observed 2.x contracts, not a general PHP process recovery guarantee.

The reporter-failure HTTP case intentionally measures the current extra output buffer, then removes only the fixture's new buffers before checking restoration. It does not modify the library. If that separate bug is fixed, update the expected contract and guide together.

On failure, retain the diagnostic and isolated application's Composer lock/version record. Fix the affected example or documentation against the selected library API, then rerun only affected scenarios before the full bounded verifier. Do not debug a fatal case inside an agent/application process.

Use the same script with actual PHP 7.4 and modern runtimes, resolving compatible application dependencies per runtime. Source lint, style checks, official skill validation and this behavioral check are separate evidence. None authorizes publication or a production change.
