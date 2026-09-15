# API and composition

All classes are under `CoiSA\ErrorHandler`. Handler interfaces remain small so reporting, application termination, HTTP rendering and event dispatch can be composed explicitly.

## Lifecycle

```php
$handler = new ErrorHandler($reporter, $optionalPhpErrorHandler, $optionalShutdownHandler);
$handler->register();
try {
    // Work that must return or propagate to a caller.
} finally {
    $handler->unregister();
}
```

Use this scoped form for code whose caller handles propagated exceptions. For process-wide uncaught exceptions, register at application bootstrap and keep the handler active through termination, as in [`examples/cli.php`](../examples/cli.php). PHP executes `finally` before dispatching an uncaught exception, so unregistering there intentionally returns responsibility to the previous exception handler.

Registration pushes PHP callbacks onto the native stacks. Previous callbacks are restored, including their error-level masks, when unregistered in reverse order. They are **not automatically called** while this handler is active. A second registration of the same instance is a no-op, not a nested reference count. Shutdown registration happens once per instance because PHP provides no shutdown-unregister function; inactive instances do nothing at shutdown. Reuse an instance when practical: PHP retains registered shutdown callbacks until process exit.

Only the instance owning both current PHP callbacks receives shutdown events. If another integration replaces a callback, restore it before unregistering this instance. Otherwise `unregister()` rejects the operation without altering either stack.

| API | Behavior |
| --- | --- |
| `ErrorHandlerInterface::register(): void` | Installs error, exception and shutdown handling |
| `ErrorHandlerInterface::unregister(): void` | Restores previous error/exception callbacks; deactivates shutdown |
| `ThrowableHandlerInterface::handleThrowable(Throwable): void` | Reports the original object; a direct call does not exit |
| `PhpErrorHandlerInterface::handlePhpError(int, string, string, int): void` | Processes a PHP error with severity, message, file and line |
| `ShutdownHandlerInterface::handleShutdown(): void` | Handles a shutdown notification |

`ErrorHandler` defaults to `ThrowErrorExceptionPhpErrorHandler` and `PhpLastErrorShutdownHandler`. The former respects the current `error_reporting()` bitmask (including `@`) and converts reportable errors to the package's `ErrorException`. The outer `ErrorHandler` delivers that exception and then resumes normal execution for recoverable warnings. The shutdown handler inspects `error_get_last()` for `E_ERROR`, `E_PARSE`, `E_CORE_ERROR` or `E_COMPILE_ERROR`; it does not turn fatal termination into recovery.

A reporter that throws is marked failed and is not retried during the same registration. Recursive calls through the same `ErrorHandler` raise `LogicException`. Direct calls propagate reporter failures; uncaught reporter failures fall back to PHP's native failure path. `unregister()` followed by `register()` begins a fresh lifecycle. The library does not choose an alternative logger or retry remote delivery.

## Reporter composition and PSR-14

`CallableThrowableHandler` adapts `callable(Throwable): void`. `ThrowableHandlerAggregate` accepts variadic reporters and supports `attach()`; it calls them in attachment order with the same throwable object. It stops if a reporter throws. Use a custom composite when independent best-effort reporting is required.

`DispatchThrowableHandler` dispatches the throwable itself through a PSR-14 dispatcher. `DispatchErrorEventThrowableHandler` dispatches an `ErrorEvent` containing it. `ThrowableListenerProvider` and `ErrorEventListenerProvider` select compatible events; the callable listeners adapt application callbacks. `LogErrorEventListener` forwards an error event to a PSR-3 logger. Event stringification contains exception details and is intended for private diagnostic sinks, not untrusted HTTP output.

## PSR-15/PSR-17 HTTP

```php
$streams = new ThrowableStreamFactory($psr17Factory, $reporter);
$responses = new ThrowableResponseFactory($streams, $psr17Factory);
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $responses);
$response = $middleware->process($serverRequest, $requestHandler);
```

Use the `Http\Message` and `Http\Middleware` namespaces for these classes. The PSR-17 factory must implement both stream and response factories. A complete runnable composition is in [`tests/Http/router.php`](../tests/Http/router.php).

The middleware returns normal downstream responses unchanged and catches downstream `Throwable` objects to generate an error response. It always unregisters its handler after processing. Use a distinct handler instance for each nested middleware scope; do not share/pre-register one instance across simultaneous requests or fibers. PHP's global callback stacks are process state, not request-local storage.

`ThrowableStreamFactory` captures reporter output. Empty output maps to `Internal Server Error`; `0` is a valid body. The factory cleans buffers it opens if rendering fails. Renderers must balance their own successful nested buffers and must not close the caller's buffers. `ThrowableResponseFactory` keeps integer codes 400–599, uses 500 otherwise, and lets the PSR-17 factory supply the standard reason phrase.

Neither the middleware nor the global handler emits PSR-7 responses to a server. The application/framework owns emission, headers, content type and transport. A PHP engine fatal error bypasses normal middleware recovery; shutdown reporting cannot reliably replace an already-started HTTP response.

## PSR-11 container integration

`Container\ConfigProvider` exposes `__invoke()`, `getDependencies()`, `getAliases()` and `getFactories()` for compatible containers such as Laminas ServiceManager. `ErrorHandlerContainer` can wrap a parent PSR-11 container, delegates known services to it, and caches services built by the library factories.

Provide `ThrowableHandlerInterface` for a custom reporter. Otherwise `ThrowableHandlerAggregateFactory` composes any available callable/event-dispatch reporters; an empty aggregate intentionally produces no diagnostics. HTTP factories also require PSR-17 `ResponseFactoryInterface` and `StreamFactoryInterface` services. PSR-14 integration needs `EventDispatcherInterface`. These implementations belong to the application; the package ships interfaces and adapters, not a logger, event bus or HTTP framework.
