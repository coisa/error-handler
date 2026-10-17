# HTTP response and reporting boundaries

Read when integrating PSR-15 middleware. The complete
[HTTP program](../examples/http.php) connects imports, autoloader, PSR-17
implementation, lifecycle handler, private reporter, response factory and request
handler. Follow its [setup and expected result](../examples/README.md#explicit-http-policy).

In this 2.4 contract the middleware registers the scoped lifecycle handler,
executes the next handler, sends a caught throwable to
`ThrowableResponseFactoryInterface` and restores callbacks in `finally`.
It does not independently report the caught throwable first. The bundled
application `PublicThrowableResponseFactory` reports privately once and builds
a fixed response. A reporter failure stops response construction and propagates;
PSR-17 failures also propagate. The application supplies the emitter.

Keep a separate lifecycle handler for each nested request/process scope.
A fresh middleware wrapper does not make a cached handler fresh.

## Optional library JSON composition

`JsonThrowableResponseFactory` and `ReportingThrowableResponseFactory` were
published in 2.5.0; select `coisa/error-handler ^2.5` and verify the installed
source for this composition. The ordinary application recipe retains its `^2.4` baseline.
Follow the [availability, direct JSON and provider recipe](json.md), which checks
the selected installation first. The pure JSON renderer preserves integer HTTP
400–599 codes; the reporting decorator supplies one private report for the 2.x
middleware. Trusted configuration explicitly selects development diagnostics.
The fixed application example above remains usable without these additive APIs.

## Existing captured-output default

The library's existing `ThrowableStreamFactory` invokes its configured reporter
and captures that reporter's output. The output becomes the response body.
Empty output or `"0"` falls back to exception text, so a silent reporter is not a
safe public renderer. Writing JSON through `echo` can produce a body but couples
diagnostics to public content; the application response recipe avoids that
coupling and does not require output buffering.

The built-in `ThrowableResponseFactory` maps integer codes 400–500 inclusive
to status; other integers and string codes use 500. It uses diagnostic exception
messages as reason phrases where accepted. From 2.4.2, forbidden controls omit
the reason argument and choose the application's PSR-17 default; valid text
(including tab/high bytes) can still reveal details. Empty messages remain
explicit. This repair does not make the renderer public-safe.

Captured-output failure triggers best-effort cleanup of removable buffers above
the entry level. Cleanup stops at a protected buffer, failed close or no progress;
caller buffers are not deliberately closed. Starting with 2.4.1, cleanup can
continue after an output callback throws if PHP removed its buffer and progress
remains possible; 2.4.0 stops cleanup at that throw. If cleanup returns, the
original primary failure propagates. A callback that exits or triggers an engine
fatal can prevent propagation. Side effects cannot be undone.

## Verification

Assert exact public body, status, content type, one original private report and
callback restoration after both success and renderer/reporter failure. For
capture, also observe caller bytes/levels and primary failure identity. Test
protected/terminating buffers only in bounded children using the application's
normal PHPUnit setup. Do not override native functions, suppress unrelated
warnings or claim recovery from process termination.
