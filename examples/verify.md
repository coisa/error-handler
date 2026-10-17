# Bounded example verification

Run `php examples/verify.php` from a source checkout after installing its
development/application dependencies from [the cookbook](README.md). PHP CLI must
provide `proc_open`; the verifier uses the directly declared Symfony Process
development dependency. No shell, real server, credentials or production data is
needed.

Optional `ERROR_HANDLER_AUTOLOAD` selects an isolated Composer autoloader for both
the verifier and example children. It must provide Symfony Process and the
example's application dependencies. The default is the checkout's
`vendor/autoload.php`. Resolve compatible versions with the actual PHP runtime;
a Composer platform setting alone does not prove minimum-runtime behavior.

The verifier starts one child per fixed scenario through Symfony Process using
its own `PHP_BINARY`, `php -n`, 64 MiB of PHP memory, a five-second timeout and an
interruption threshold of 16 KiB combined stdout/stderr. `finally` calls `stop(0)`.
The output callback throws only once, preserving that failure if cleanup drains
more output. A final chunk can arrive during cleanup; the threshold does not
claim exact byte-level retained-buffer size. No custom process helper is used.

Inherited environment names are explicitly disabled, then the fixture-only
`ERROR_HANDLER_BOUNDED_FIXTURE` flag and optional `ERROR_HANDLER_AUTOLOAD` are
supplied. The parent environment remains unchanged. The guarded native-fatal
case cannot run as an ordinary example. Children use synthetic input and own
their callbacks/buffers; they do not spawn descendants.

Success prints twenty-four scenario results and the actual PHP version, then exits 0.
These include callable PHP-error policy, direct PSR-3 logging's exact
messages/original context and explicit filtering of synthetic deprecations while
preserving the general branch. Best-effort reporting retains the original and
every ordered reporter failure after partial success.
The separate JSON recipe preserves an HTTP 503 and reports the original privately
once before pure rendering, both for generic output and explicitly selected debug
metadata. Debug verifies trace arguments/objects are absent and no-store is set;
no application output buffer or emitter is involved.

The optional Whoops recipe adds public/development selection with a fresh configured
page, HTTP 503 and one private report. Public mode never constructs Whoops; HTML
mode requires the optional package. It does not negotiate request headers or emit
the response. Selected hidden superglobal keys are not comprehensive redaction.

JSON/Whoops/provider recipes need `coisa/error-handler:^2.5`, the published floor
for these APIs. The common reporting baseline remains `^2.4`.

For an application that also installs `fast-forward/container:^1.9` at runtime,
use PHP 8.3+ and select `php examples/verify.php --with-fast-forward`. It adds two
Interop JSON and two Whoops profile cases, for twenty-eight bounded scenarios in total. They
resolve the application PSR-17/reporter definitions, use a fresh middleware scope
and verify public/default or explicitly configured development JSON, plus cached
HTML page services across distinct scopes. The default page is lazy; the configured
mode binds a page prepared from application Config before composition. Both use
one aggregate ordered base, Whoops, application. The HTML cases need the
optional Whoops dependency. Requesting
the option without its PHP/container prerequisites fails before starting those
scenarios; the default core run remains available without FastForward on PHP 7.4.

Unexpected status/output, timeout
or output threshold causes a nonzero verifier exit after cleanup. Registered
uncaught delivery currently reports and exits 0; native compile fatal reports at
shutdown and exits 255. Neither is a general recovery guarantee.

The reporter-failure HTTP case checks removable capture cleanup before its final
safety cleanup and verifies callback restoration. Protected buffers and arbitrary
output handlers have separate PHPUnit process cases and documented limits in
[testing](../docs/testing.md).

On failure retain diagnostics and the fixture's Composer lock/version record.
Fix the affected example or documentation against its selected API, then rerun
the affected case and the full verifier. Never move a fatal experiment into the
parent test/application process. Style, unit coverage, installed-package checks
and these executable examples remain different evidence. None authorizes merge,
release, Pages publication or a production change.
