# HTTP response and reporting boundaries

Read when integrating PSR-15 middleware. The complete
[HTTP program](../examples/http.php) connects imports, autoloader, PSR-17
implementation, lifecycle handler, private reporter, pure response factory and
request handler. Follow its [setup and result](../examples/README.md#explicit-http-policy).

Middleware requires one scoped object implementing both
`ErrorHandlerInterface` and `ThrowableHandlerInterface`. It registers that scope,
executes downstream, reports a caught throwable once, invokes the pure
`ThrowableResponseFactoryInterface` and restores callbacks in `finally`.
A reporter failure propagates before rendering. A renderer/PSR-17 failure also
propagates without retrying the original report. The application supplies the
HTTP emitter; reporters must keep diagnostics in private sinks.

The bundled application `PublicThrowableResponseFactory` only builds a fixed
status-500 JSON response. It does not report or inspect diagnostic output.
This is application example code, not a built-in JSON adapter or configurable
library API. Keep distinct handlers for nested process/request scopes. Invoke
the provider's middleware factory service for each fresh scope; repeated `get()`
calls return the cached middleware.

## Select a built-in renderer

Use [JSON](json.md) for a generic JSON body or explicitly configured diagnostic
JSON. Use [Whoops](whoops.md) only after trusted application configuration selects
development HTML and provides its optional runtime dependency. Both factories
preserve integer throwable codes 400–599, otherwise use 500, and leave the reason
to the application's PSR-17 implementation. Their independent profiles select
the pure renderer; they do not import lifecycle or reporter maps.

`ReportingThrowableResponseFactory($reporter, $renderer)` remains available for
an application pipeline that has not already reported. It calls the reporter,
then returns the pure renderer's response. Do not select it for this middleware:
that would report the same original twice. A reporter failure prevents rendering;
a renderer failure does not cause a retry. The application decides which layer
owns reporting before combining these components.

## Generic default and explicit output capture

The provider's `GenericThrowableStreamFactory` returns `Internal Server Error`
without invoking a reporter. `ThrowableResponseFactory` preserves integer codes
400–599 and uses 500 otherwise, including string codes. It leaves the reason
phrase to the application's PSR-17 implementation; exception messages are never
the reason phrase.

The retained `ThrowableStreamFactory` is an explicit compatibility adapter for
deliberately public echo-based rendering. Its injected public renderer's output
becomes the stream: empty output becomes `Internal Server Error`, while `"0"`
remains a valid body. Never inject the private reporter middleware already called.
The built-in JSON and Whoops response factories return their own bodies directly.

Capture reads only when a buffer remains above the caller's entry depth; otherwise
it uses the generic body without reading the caller's existing output.
Capture cleanup removes reachable removable buffers above the caller's entry
level. It stops at protected buffers or no progress, retaining the first cleanup
failure. A renderer failure takes precedence; otherwise a cleanup failure
propagates. Renderers must balance their buffers and never close caller-owned
buffers. Native exit/fatal termination can prevent cleanup or propagation;
side effects cannot be undone.

## Verification

Assert exact public body, status, content type, one original private report,
report-before-render ordering and restoration after success or either failure.
For capture, also observe caller bytes/levels and primary failure identity. Test
protected/terminating buffers only in bounded children using the application's
normal PHPUnit setup. Do not override native functions, suppress unrelated
warnings or claim recovery from process termination.
