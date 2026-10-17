# API and composition

All classes are under `CoiSA\ErrorHandler`. The PHP requirement is `^8.3`;
CI targets PHP 8.3–8.5. Complete runnable integrations are in
the [cookbook](examples.md).

## Lifecycle and diagnostic reporting

```php
$errors = new ErrorHandler($reporter, $optionalPhpErrorHandler, $optionalShutdownHandler);
$errors->register();
try {
    // Work whose caller owns propagated failures.
} finally {
    $errors->unregister();
}
```

For process-wide uncaught handling, keep the handler registered through termination,
as in [the CLI example](https://github.com/coisa/error-handler/blob/master/examples/cli.php). PHP executes finally before dispatching
an uncaught exception, so unregistering there returns responsibility to the previous
handler. Direct `handleThrowable()` delivers the original object and returns; the
registered uncaught callback exits 1 when reporting returns normally.

Registration installs process-wide PHP callbacks. Unregister in reverse order,
restoring earlier callback identities and severity masks. Previous callbacks are
not automatically called while this handler is active. Re-registering an active
instance is a no-op. PHP retains one shutdown callback per instance; inactive
instances do nothing. Reuse handlers for sequential work and use distinct instances
for nested scopes. These stacks do not provide concurrent-request/fiber isolation.

| Contract | Responsibility |
| --- | --- |
| `ErrorHandlerInterface` | Register and unregister lifecycle callbacks |
| `ThrowableHandlerInterface::handleThrowable(Throwable): void` | Deliver a diagnostic without producing an HTTP representation |
| `PhpErrorHandlerInterface` | Apply policy to PHP severity/message/file/line |
| `ShutdownHandlerInterface` | Handle a shutdown notification |

The required `$throwableHandler` constructor argument accepts
`ThrowableHandlerInterface|callable`. Optional `$phpErrorHandler` and
`$shutdownHandler` accept their matching interface, a callable or null. The
parameter names and order are unchanged. Interface instances retain their identity
and take precedence over `__invoke()` when an object supports both contracts.
Other callables are wrapped with the existing `CallableThrowableHandler`,
`CallablePhpErrorHandler` or `CallableShutdownHandler`, without being invoked
during construction. Invalid non-callable inputs raise TypeError at that boundary.

Callbacks receive one Throwable, four PHP-error arguments, or no shutdown
arguments respectively; their return values are ignored. Explicit adapter objects
remain useful when another API requires an interface. Null and omitted options
retain the default policies below, including shutdown reporting through the same
ErrorHandler. The [complete callback recipe](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php) supplies
all three callables and pairs active shutdown notification with cleanup.

The default `ThrowErrorExceptionPhpErrorHandler` honors `error_reporting()` and
suppression, converts reportable errors and lets the outer handler report them.
Recoverable warnings continue. The default `PhpLastErrorShutdownHandler` inspects
`E_ERROR`, `E_PARSE`, `E_CORE_ERROR` and `E_COMPILE_ERROR`, without recovery.
A fatal inside another shutdown callback aborts the remaining queue even for a
fresh handler; adding generations cannot rescue that engine path.

Only the active lifecycle handles shutdown, including custom shutdown after an
uncaught throwable. Replacing a callback transfers ownership away. Restore it
before unregistering; otherwise unregister rejects the operation without altering
the native stacks.

A reporter failure suppresses retries for the current registration. Recursion
through the same handler raises LogicException. Independent unregistered direct
calls retain no failed flag. Unregister/register starts a fresh lifecycle.
No alternative logger or remote retry is installed implicitly.
These failure boundaries handle thrown Throwable objects; an application callback
that calls exit/die or causes a native fatal does not become recoverable.

## Callable reporters, aggregates and PSR-14

`CallableThrowableHandler` adapts `callable(Throwable): mixed` and ignores its
return value.
`ThrowableHandlerAggregate` accepts variadic reporters and `attach()`, delivering
the same object in order and stopping on the first failure.

`DispatchThrowableHandler` dispatches the original throwable through PSR-14.
`DispatchErrorEventThrowableHandler` dispatches an `ErrorEvent` wrapper.
Under `CoiSA\ErrorHandler\EventDispatcher`,
`ListenerProvider\ThrowableListenerProvider` selects Throwable objects;
`ListenerProvider\ErrorEventListenerProvider` selects the concrete built-in
`Event\ErrorEvent`, not arbitrary implementations of `ErrorEventInterface`.
Both accept variadic callable listeners and `attach()` in order; lookup selects
listeners without invoking them. The application dispatcher performs delivery.

`Listener\ThrowableCallableListener` and `Listener\ErrorEventCallableListener`
invoke their callbacks, ignore callback results and return the same throwable or
event respectively. Their callback failures propagate. `LogErrorEventListener`
forwards the diagnostic event to an application PSR-3 logger.
`ErrorEvent::getThrowable()` preserves the original object; stringifying the
event includes private diagnostics and must not produce a public HTTP body.
See [the complete event/logger example](examples.md#events).

## Direct PSR-3 logging

`Handler\LogThrowableHandler($logger, $logLevel = LogLevel::ERROR, $context = [])`
retains its compatible 2.x behavior. It calls the application's PSR-3 logger once
with the configured level, the exact throwable message (including an empty string)
and the original object under `context['exception']`. That reserved entry replaces
a caller's exception entry without mutating the caller's array; other context
values retain their identity and types. Context-key order is not a contract.

The level is forwarded unchanged, without inference from PHP severity, exception
code or HTTP status. The logger decides which levels it supports. Logger failure
propagates unchanged; the adapter adds no retry, output or callback registration.
It is not automatically attached when a logger is available in the container.
Construct it explicitly or bind `ThrowableHandlerInterface` using the
[merged-provider override](fast-forward.md#required-overrides).

See [the complete logging example](https://github.com/coisa/error-handler/blob/master/examples/logging.php). Unlike this direct
adapter, `LogErrorEventListener` retains its event-string message policy.

## Conditional reporting

`Handler\FilteringThrowableHandler($reporter, $predicate)` wraps an explicit
`ThrowableHandlerInterface`. Its required `callable(Throwable): bool` predicate
runs once per invocation: true forwards the same object once, false skips only
that delegate. Repeated calls with the same object remain independent deliveries.
Non-boolean results raise TypeError; predicate and delegate failures propagate
unchanged, without retry. A failure still stops the default fail-fast aggregate.

No output, default filtering policy, registration or automatic aggregate discovery
is added. A skipped report does not recover the operation, change PHP suppression
or choose the HTTP response. Rendering remains a separate public
boundary regardless of whether a diagnostic reporter forwards the event.
See [the filtering example](https://github.com/coisa/error-handler/blob/master/examples/filtering.php); select PHP severity via
ErrorException::getSeverity(), not an arbitrary exception code.

## Callable PHP-error policy

`Handler\CallablePhpErrorHandler($callback)` retains its compatible 2.x contract:
the callback receives `(int $code, string $message, string $filename, int $line)`
once, unchanged. The interface returns void, so every callback result, including
false/null, is ignored. Returning false does not delegate to PHP's native handler.
There is no fifth errcontext argument, automatic mask check, conversion or output.

The callback owns suppression/conversion policy and its failures propagate
unchanged. Through ErrorHandler, a thrown ErrorException follows the normal
reporting boundary; other failures propagate. The [complete PHP-error example](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php)
explicitly checks `error_reporting() & $code` before converting an enabled error.

Pass the callable directly as the second ErrorHandler argument, or keep the
explicit wrapper when an interface instance is needed. The provider's explicit
handler factory also accepts either form under `PhpErrorHandlerInterface`;
use FastForward's `ServiceFactory` to bind a callback as a value rather than a
construction factory. Null keeps the existing default. Construction and discovery
never register native callbacks.

`Exception\ErrorException::fromPhpError($message, $code, $severity, $filename, $line, $previous)`
keeps exception code and PHP severity independent and preserves the original
previous throwable. Omitted or null location values become an empty filename
and line 0; the factory does not invent a source location or previous cause.

## HTTP: report once, then render explicitly

`ErrorHandlerMiddleware` accepts an
`ErrorHandlerInterface&ThrowableHandlerInterface` instance and a
`ThrowableResponseFactoryInterface`. The built-in ErrorHandler already implements
both contracts. A custom lifecycle implementation must support reporting too.

On a downstream Throwable, middleware first calls the scoped handler, then asks
the response factory for a public representation. A reporter failure propagates
after cleanup; its failed-registration guard prevents a second reporter attempt
when a PHP warning caused that failure. Rendering failure also propagates after
cleanup. Successful downstream responses pass through unchanged.

Diagnostic reporters keep their `void` contract, work in CLI/events/shutdown,
and do not need PSR-7. In HTTP, send diagnostics to private sinks. Reporter echo
is unsolicited output; the default middleware does not treat it as the response
body. Pure renderers return StreamInterface or ResponseInterface directly.

```php
$streams = new GenericThrowableStreamFactory($psr17Factory);
$responses = new ThrowableResponseFactory($streams, $psr17Factory);
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($privateReporter), $responses);
$response = $middleware->process($request, $application);
```

Imports are under `Http\Message` and `Http\Middleware`. The PSR-17 implementation
must provide response and stream factories. The default generic renderer always
returns `Internal Server Error`, without reporter invocation or buffering.
`ThrowableResponseFactory` keeps integer codes 400–599, otherwise uses 500, and
lets PSR-17 supply the standard reason phrase.

Implement `ThrowableStreamFactoryInterface` for a pure custom body, such as JSON,
or `ThrowableResponseFactoryInterface` when also choosing Content-Type/headers.
See [JSON without echo](examples.md#http-rendering). The application owns emission,
headers, transport and public payload policy; diagnostic details are never a safe
default. The pure `JsonThrowableResponseFactory` and optional
`WhoopsThrowableResponseFactory` retained from 2.5.0 provide built-in alternatives;
see [HTTP composition](http.md). `ReportingThrowableResponseFactory` remains an
explicit utility for other pipelines, but must not wrap the renderer after this
middleware has already reported.

## Optional output-capture adapter

`ThrowableStreamFactory` remains available explicitly for public echo-based
templates. In that adapter only, output from its injected public reporter becomes the
stream; a JSON callback would need to echo JSON. It is not the provider default.

Pass a separate public renderer, not the diagnostic reporter middleware already
called. Empty captured output becomes `Internal Server Error`; `0` remains valid.
Before reading output, the adapter checks that a buffer remains above the caller's
entry depth. If none remains, it uses the generic body instead of reading a caller buffer.
The adapter cleans reachable removable buffers after success and failure and preserves a
renderer failure over cleanup failure. Cleanup stops at a non-removable buffer
or lack of progress. Renderers must balance their own removable buffers and never
close caller-owned buffers. See [the capture recipe](examples.md#output-capture).

## Interop provider

`ServiceProvider\ErrorHandlerServiceProvider` uses FastForward Container's
required runtime resolution, caching and constructor autowiring. It never
registers PHP callbacks during discovery/construction. The explicit lifecycle
factories live under `ServiceProvider\Factory`; no parallel standalone container
or native Laminas ConfigProvider remains.

Use `container(ErrorHandlerServiceProvider::class, $application)` to supply
application dependencies and optional reporters. To override existing library
service IDs, merge library definitions first and the application last in one
AggregateServiceProvider. Bind required reporters/renderers explicitly through
their interfaces. The default
reporter aggregate discovers optional callable/PSR-14 reporters and is silent when
none are configured. The default HTTP stream alias resolves GenericThrowableStreamFactory.
Applications supply PSR-14, PSR-17 and logger implementations. See [provider wiring](fast-forward.md)
and [migration](migration.md).

## Best-effort reporting

`Handler\BestEffortThrowableHandlerAggregate` is an explicit alternative to the
existing fail-fast aggregate. Its fixed constructor list is inert. Each invocation
attempts every slot once, in order, with the identical original Throwable; duplicate
slots are deliberate separate attempts. Zero/all-success passes return normally.
If any reporter throws, including partial success, one
`Exception\AggregateReportingException` escapes after all attempts.

`getOriginalThrowable()` and `getPrevious()` retain the original; `getFailures()`
returns the nonempty ordered list of identical reporter failures. Its constructor
requires the original, one failure and optional remaining failures. The summary is
fixed, but stored causes/failures and exception strings remain private diagnostics.
Use the [complete recipe](https://github.com/coisa/error-handler/blob/master/examples/best-effort.php) for explicit terminal handling.

No retry, deduplication, reentry guard or durable transport is added. Native
termination can prevent later attempts. Default provider composition stays
fail-fast; no factory/discovery selects this adapter. In middleware, an aggregate
reporting failure propagates before rendering and does not become a public body.
