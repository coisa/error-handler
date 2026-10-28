# HTTP reporting and response boundaries

Middleware requires one scoped object implementing `ErrorHandlerInterface` and
`ThrowableHandlerInterface`. It registers, calls downstream, reports a caught
Throwable once, passes that same Throwable and the current ServerRequestInterface
to a separate response renderer, and unregisters in `finally`.
A reporter failure propagates without rendering. Successful downstream responses
retain their exact identity; PHP warnings do not replace them automatically.
Applications supply PSR-17 factories and own HTTP emission and format selection.

All response factories implement
`createResponseFromThrowable(Throwable $throwable, ServerRequestInterface $request): ResponseInterface`.
The request parameter is required, including for renderers that ignore it.
Direct application calls must supply the current request too.

JSON and Whoops adapters were introduced in 2.5.0. Middleware owns reporting;
pass the pure renderer directly. Do not wrap it in
`ReportingThrowableResponseFactory` after this middleware has already reported.
See [migration](migration.md#separate-http-reporting-from-rendering).

## Request negotiation

`ErrorHandlerServiceProvider(bool $debug = false, bool $cli = false)` supplies one default
`NegotiatingThrowableResponseFactory` for all requests. A cached middleware can
therefore serve an HTML browser request and a JSON API request using each current
request's `Accept` header; no request is cached in a service.

| Available representation | Selection |
| --- | --- |
| Generic body | `text/plain`, or fallback when Accept is absent or no supported positive match exists |
| JSON Problem Details | `application/json` or `application/problem+json`, with generic details unless trusted debug is enabled |
| Public HTML | `text/html`, without exception details |
| Diagnostic HTML | The same Symfony renderer with trusted debug; optional Whoops requires an explicit override |

Accepted quality values determine selection. Zero-quality representations are
excluded. Equal wildcard choices prefer generic, then JSON, then HTML. Debug
enables diagnostic HTML/JSON; it never overrides the client's JSON preference.
A JSON-only request never resolves Whoops. Whoops is never selected by default,
whether installed or absent. Applications can explicitly replace the HTML
candidate with Whoops while preserving negotiation.

The HTML candidate is `text/html; charset=UTF-8`, matching the emitted response.
Bare `text/html` accepts it; a charset-specific preference or zero-quality
exclusion applies to that actual representation. Custom maps must likewise
describe their renderers' media parameters.

The fallback is an error response for the original failure, not a new 406.
Negotiated responses add Accept to Vary while preserving existing fields.
Reporting still occurs once before negotiation. Renderers receive the exact
current request and original Throwable; selecting a representation reports nothing.

For explicit composition, construct
`NegotiatingThrowableResponseFactory($fallback, $renderers)`. The map keys are
media types and values are lazy `callable(): ThrowableResponseFactoryInterface`
factories. The selected factory alone is invoked. Configure known response
services from trusted application code; client input selects only among that map.
Concrete Symfony HTML, JSON, Whoops and generic factories can still be used directly.

## Generic default and explicit output capture

The single provider selects `NegotiatingThrowableResponseFactory`; see
[request negotiation](#request-negotiation). Its fallback uses
`GenericThrowableStreamFactory`, which always returns `Internal Server Error`
without inspecting reporter output. Diagnostic reporters
must use private sinks: echoed diagnostics can still escape the response object
and corrupt application emission. `ThrowableResponseFactory` preserves integer
codes 400–599 and otherwise uses 500, including SQLSTATE strings. It leaves reason
phrases to PSR-17 instead of copying exception messages.

Implement a pure stream/response factory for an application-specific representation;
the [custom JSON-stream recipe](https://github.com/coisa/error-handler/blob/master/examples/http-rendering.php) returns a stream
directly. `ThrowableStreamFactory` remains an explicit adapter for a separate
public echo-based renderer. Its output becomes the response stream: `0` is
preserved, and empty output uses the generic body. A private reporter is not that
public renderer and must not be invoked again during rendering.

If rendering returns with no buffer above the caller's entry depth, capture uses
the generic body instead of reading the caller's existing output. The factory
does not read or close a caller-owned buffer or invoke its output callback.
Missing capture at native depth zero also returns the generic body without a
missing-buffer warning. This cannot undo the renderer's own writes, flushes or
buffer changes.
Capture cleans reachable removable renderer-owned buffers after success and
failure. It stops at protected buffers or no progress and retains the first
cleanup failure; the original renderer failure takes precedence. Renderers must
not close caller-owned buffers. Callback exit/die/fatal effects remain native
limitations. See the [capture recipe](https://github.com/coisa/error-handler/blob/master/examples/output-capture.php).

## Opt-in JSON responses and explicit reporting

`JsonThrowableResponseFactory($responses, $streams, $serializer, bool $debug = false)`
is a pure PSR-17 renderer using Symfony's `SerializerInterface`. The provider supplies
`Serializer` with `ProblemNormalizer(true)` and `JsonEncoder`; direct composition
supplies the same collaborators explicitly. Its public Problem Details body contains
`type: about:blank`, the PSR-17 reason as `title`, the HTTP `status` and
`detail: Request failed`. A direct call returns `application/problem+json`.
Negotiation also supports `application/json` and retains the requested supported
media type; both have the same Problem Details shape. Integer exception codes 400–599 are
preserved; other integers and string codes use 500. Reason phrases come from
PSR-17. No report, output capture or emission occurs inside this factory.

The [complete JSON recipe](https://github.com/coisa/error-handler/blob/master/examples/http-json.php) constructs an `ErrorHandler`
with a private reporter, then passes the JSON factory directly to middleware.
Middleware delivers the identical original once before rendering. A reporter
failure prevents rendering; renderer and PSR-17 failures propagate after callback
restoration without a second report.

The fourth parameter is an explicit application-controlled boolean. When true,
`detail` contains the exception message and JSON adds `class` and a Symfony trace.
Frame argument values and objects are excluded; an empty `args` array is harmless.
It sets `Cache-Control: no-store` and substitutes invalid UTF-8. Serialization
failures propagate; JSON rendering never falls back to HTML. Messages, paths and other metadata can still contain secrets. No environment
name or request header enables diagnostics. Use trusted configuration and strict
typing; casting the string `"false"` yields true in PHP.

`ReportingThrowableResponseFactory($reporter, $renderer)` remains a public,
explicit utility for pipelines that have not already reported. It reports once
and then delegates. Reporter failures prevent rendering; collaborator failures
propagate unchanged. Selecting it after this middleware would report
twice. The provider never selects it automatically.

## Public HTML

`HtmlThrowableResponseFactory($responses, $streams, bool $debug = false)` uses
Symfony's `HtmlErrorRenderer` to create a production error page. With debug false,
the page omits the exception message, source and trace. It returns `text/html; charset=UTF-8`,
uses the same 400–599 status policy and does not report or emit.
The default provider always offers Symfony HTML. Trusted debug enables Symfony's
diagnostic page with a document shell and the renderer's stylesheet;
public mode omits diagnostic details. Whoops presence does not
change either default.
The [complete public/debug HTML recipe](https://github.com/coisa/error-handler/blob/master/examples/http-html.php)
shows explicit reporting followed by a direct request-aware render. The
[manual middleware recipe](https://github.com/coisa/error-handler/blob/master/examples/http-whoops.php)
shows public HTML or explicitly selected diagnostic Whoops.

## Opt-in development HTML with Whoops

Install `filp/whoops:^2.18.7` in the application wherever the optional Whoops
renderer is explicitly selected. This minimum includes upstream PHP 8.5 deprecation and native STDIN /
`php -r` source-inspection fixes. Composer rejects older Whoops versions when
selected without making that optional package mandatory.
`WhoopsThrowableResponseFactory($responses, $streams, $pageFactory)`
takes a callable returning `Whoops\Handler\PrettyPageHandler`, invoked lazily for
each render. Its constructor signature is unchanged from 2.5.0. Pass this pure renderer
directly to middleware; see [manual selection](https://github.com/coisa/error-handler/blob/master/examples/http-whoops.php).

Each render owns a fresh, unregistered `Whoops\Run`, response and body. Whoops
returns HTML without writing output, sending headers or terminating the process.
The response uses `text/html; charset=UTF-8`, `no-store`, the same 400–599 status
policy and PSR-17 reason phrases. The application may return a configured page
service for sequential renders: current diagnostic data is replaced while page
configuration remains. That page retains its latest diagnostic and does not
provide concurrent-request isolation.

The [Whoops provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-whoops.php)
uses `container($application, new ErrorHandlerServiceProvider(debug: true))`.
The same provider supplies JSON, generic responses and Symfony HTML; the
recipe explicitly overrides its negotiating HTML candidate with optional Whoops.
Override the `PrettyPageHandler` service through an application `ServiceFactory`.
The page is lazy and cached; middleware scopes and runs are fresh. See
[provider configuration and failure limits](fast-forward.md#development-html-configuration).

Choose diagnostic HTML only through trusted development configuration. Default
Whoops pages expose globals, source and traces; selected key hiding is not complete
redaction. The concrete Whoops renderer does not negotiate; the
application can explicitly select it as the negotiating factory's HTML candidate.
The default provider uses Symfony HTML in both public and debug modes.
No request value activates debug.

## Failure and lifetime boundaries

Best-effort reporting attempts all its slots and throws if any failed. Middleware
propagates that aggregate failure before invoking its renderer; it does not create
a fallback response. The aggregate's original and failures are not a redaction
policy. Applications own terminal handling and private sinks.

Verify exact body, status, one original private report, report-before-render
ordering and callback restoration for success and failures. See [API](api.md),
[provider composition](fast-forward.md), [cookbook](examples.md) and [tests](testing.md).
