# HTTP boundaries in the unreleased major

Middleware requires one scoped object implementing `ErrorHandlerInterface` and
`ThrowableHandlerInterface`. It registers, calls downstream, reports a caught
Throwable once, invokes a separate response renderer and unregisters in `finally`.
A reporter failure propagates without rendering. Successful downstream responses
retain their exact identity; PHP warnings do not replace them automatically.

The provider selects `GenericThrowableStreamFactory`, which always returns
`Internal Server Error` without inspecting reporter output. Diagnostic reporters
must use private sinks: echoed diagnostics can still escape the response object
and corrupt application emission. The response factory preserves integer codes
400–599 and otherwise uses 500, including SQLSTATE strings; it leaves reason
phrases to PSR-17 instead of copying exception messages.

Implement a pure stream/response factory for application JSON or public HTML.
The [JSON recipe](../examples/http-rendering.php) returns a stream directly.
`ThrowableStreamFactory` is an explicit compatibility adapter for a separate public
echo-based renderer. It preserves `0`, uses the generic body for empty output and
cleans reachable renderer-owned buffers after success and failure. The original
renderer failure takes precedence over cleanup failure. Caller-owned/protected
buffers and callback exit/die/fatal side effects remain native limitations.

See [API](api.md), [provider composition](fast-forward.md), [cookbook](examples.md)
and [tests](testing.md). Applications own HTTP emission and content negotiation;
JSON/dev-HTML adapters remain separate proposals.
