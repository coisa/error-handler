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

Success prints eighteen scenario results and the actual PHP version, then exits 0.
These include direct PSR-3 logging's exact messages/original context and explicit
filtering of synthetic deprecations while preserving the general branch.
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
