# HTTP boundaries in the unreleased major

Middleware requires one scoped object implementing `ErrorHandlerInterface` and
`ThrowableHandlerInterface`. It registers, calls downstream, reports a caught
Throwable once, invokes a separate response renderer and unregisters in `finally`.
A reporter failure propagates without rendering. Successful downstream responses
retain their exact identity; PHP warnings do not replace them automatically.
Applications supply PSR-17 factories and own HTTP emission and format selection.

The JSON and Whoops adapters published in 2.5.0 remain available in this source.
The major changes their middleware composition: pass the pure renderer directly.
Do not wrap it in `ReportingThrowableResponseFactory` after this middleware has
already reported. See [migration](migration.md#separate-http-reporting-from-rendering).

## Generic default and explicit output capture

The base provider selects `GenericThrowableStreamFactory`, which always returns
`Internal Server Error` without inspecting reporter output. Diagnostic reporters
must use private sinks: echoed diagnostics can still escape the response object
and corrupt application emission. `ThrowableResponseFactory` preserves integer
codes 400–599 and otherwise uses 500, including SQLSTATE strings. It leaves reason
phrases to PSR-17 instead of copying exception messages.

Implement a pure stream/response factory for an application-specific representation;
the [custom JSON-stream recipe](../examples/http-rendering.php) returns a stream
directly. `ThrowableStreamFactory` remains an explicit adapter for a separate
public echo-based renderer. Its output becomes the response stream: `0` is
preserved, and empty output uses the generic body. A private reporter is not that
public renderer and must not be invoked again during rendering.

Capture cleans reachable removable renderer-owned buffers after success and
failure. It stops at protected buffers or no progress and retains the first
cleanup failure; the original renderer failure takes precedence. Renderers must
not close caller-owned buffers. Callback exit/die/fatal effects remain native
limitations. See the [capture recipe](../examples/output-capture.php).

## Opt-in JSON responses and explicit reporting

`JsonThrowableResponseFactory($responses, $streams, bool $debug = false)` is a
pure PSR-17 renderer. Public mode returns exactly `{"error":"Request failed"}`
with `Content-Type: application/json`. Integer exception codes 400–599 are
preserved; other integers and string codes use 500. Reason phrases come from
PSR-17. No report, output capture or emission occurs inside this factory.

The [complete JSON recipe](../examples/http-json.php) constructs an `ErrorHandler`
with a private reporter, then passes the JSON factory directly to middleware.
Middleware delivers the identical original once before rendering. A reporter
failure prevents rendering; renderer and PSR-17 failures propagate after callback
restoration without a second report.

The third parameter is an explicit application-controlled boolean. When true,
JSON adds exception class, message, file, line and trace metadata, omitting frame
arguments and objects. It sets `Cache-Control: no-store` and substitutes invalid
UTF-8. Messages, paths and other metadata can still contain secrets. No environment
name or request header enables diagnostics. Use trusted configuration and strict
typing; casting the string `"false"` yields true in PHP.

`ReportingThrowableResponseFactory($reporter, $renderer)` remains a public,
explicit utility for pipelines that have not already reported. It reports once
and then delegates. Reporter failures prevent rendering; collaborator failures
propagate unchanged. Selecting it after this major's middleware would report
twice. The base provider and both response profiles never select it automatically.

## Opt-in development HTML with Whoops

Install `filp/whoops:^2.18.4` in the application wherever diagnostic HTML is
selected. `WhoopsThrowableResponseFactory($responses, $streams, $pageFactory)`
takes a callable returning `Whoops\Handler\PrettyPageHandler`, invoked lazily for
each render. The manual signature is unchanged from 2.5.0. Pass this pure renderer
directly to middleware; see [manual selection](../examples/http-whoops.php).

Each render owns a fresh, unregistered `Whoops\Run`, response and body. Whoops
returns HTML without writing output, sending headers or terminating the process.
The response uses `text/html; charset=UTF-8`, `no-store`, the same 400–599 status
policy and PSR-17 reason phrases. The application may return a configured page
service for sequential renders: current diagnostic data is replaced while page
configuration remains. That page retains its latest diagnostic and does not
provide concurrent-request isolation.

The [Whoops provider recipe](../examples/provider-whoops.php) composes independent
base, Whoops and application maps in one aggregate. The profile has no constructor:
override the `PrettyPageHandler` service through an application `ServiceFactory`.
The page is lazy and cached; middleware scopes and runs are fresh. See
[provider configuration and failure limits](fast-forward.md#development-html-profile).

Choose diagnostic HTML only through trusted development configuration. Default
Whoops pages expose globals, source and traces; selected key hiding is not complete
redaction. No Accept negotiation or automatic debug activation is supplied.

The known [STDIN trace limit](https://github.com/coisa/error-handler/issues/99)
was reproduced on PHP 8.5.11 with Whoops 2.18.6: reading `Standard input code`
produces a distinct PHP warning in Whoops `Frame.php`. An active PHP reporter may
receive that additional event; a failing reporter can interrupt rendering. This
is not another delivery of the original. No global suppression or workaround is
installed. The ordinary combined recipes run from real files.

## Failure and lifetime boundaries

Best-effort reporting attempts all its slots and throws if any failed. Middleware
propagates that aggregate failure before invoking its renderer; it does not create
a fallback response. The aggregate's original and failures are not a redaction
policy. Applications own terminal handling and private sinks.

Verify exact body, status, one original private report, report-before-render
ordering and callback restoration for success and failures. See [API](api.md),
[provider composition](fast-forward.md), [cookbook](examples.md) and [tests](testing.md).
