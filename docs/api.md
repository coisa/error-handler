# Core API and lifecycle in 2.x

These guides describe the existing 2.x API, including compatible maintenance on `master`. Confirm the installed version with `composer show coisa/error-handler` and consult its matching source. A pending Changeset is not a published Composer version. The PSR-11 metadata and PHP 7.4 alias corrections are tracked in [#13](https://github.com/coisa/error-handler/issues/13) and [#40](https://github.com/coisa/error-handler/issues/40); container recipes on PHP 7.4 need a checkout or release containing both.

The declared runtime is PHP `^7.4 || ^8.0`. This is a dependency contract, not evidence that every PHP/dependency combination has been tested. Application integrations can impose narrower requirements.

## Start with one reporting path

A **reporter** receives a `Throwable` and delivers diagnostics to an application-selected destination. The core `ErrorHandler` connects that reporter to explicit calls or native PHP callbacks. An HTTP response factory has a separate job: creating the response that a client may see.

1. Follow the [application setup](../examples/README.md#application-dependencies), then run the [complete direct-reporting recipe](examples.md#direct-throwable-reporting). It needs no container, logger implementation or callback registration. Expect `reported: Example failure`, then `continued`.
2. Replace the synthetic output callback with the application's private reporter. Use [direct PSR-3 logging](#direct-psr-3-logging) when the application already has a logger, or [events](#events-and-logging) when its dispatcher owns delivery.
3. Choose [native callbacks](#native-callbacks) only when warnings or uncaught failures should enter that reporting path. Construction and direct reporting do not install them.
4. For a web application, choose its [public HTTP response policy](http.md#a-safe-application-response) before connecting the middleware. Private diagnostics and client-visible content need separate decisions.

| Responsibility | Library boundary | Application supplies or decides |
| --- | --- | --- |
| Diagnostic delivery | `Handler\ThrowableHandlerInterface` and built-in adapters | Destination, redaction, transport and reporter-failure policy |
| PHP callback lifetime | `ErrorHandlerInterface::register()` / `unregister()` | Bootstrap location, distinct nested scopes and process exit policy |
| Service composition | `Container\ErrorHandlerContainer`, `Container\ConfigProvider`, factories and optional Interop providers | External implementations and required service overrides |
| HTTP response creation | `Http\Message\ThrowableResponseFactoryInterface` | Public content, application status mapping and PSR-17 implementations |
| HTTP delivery | A returned PSR-7 response | Server integration and response emitter |

The [cookbook](examples.md) contains every complete executable recipe. Read [container integration](container.md) for service lookup, aliases and factories; read [testing](testing.md) for what the repository's checks actually establish.

## Choose a reporter and scope

`ThrowableHandlerInterface::handleThrowable(Throwable): void` is the reporting boundary. Applications choose the destination, redaction and delivery policy. `CallableThrowableHandler` adapts a callable; its return value is ignored. For logging through the built-in event adapter, see [events and logging](#events-and-logging).

`new ErrorHandler($reporter, $phpErrors = null, $shutdown = null)` constructs services without installing PHP callbacks. The defaults are `ThrowErrorExceptionPhpErrorHandler` and `PhpLastErrorShutdownHandler`. Calling `handleThrowable()` directly delegates and returns if the reporter returns. Reporter failures propagate.

Run [the direct example](https://github.com/coisa/error-handler/blob/master/examples/direct.php) for explicit reporting without global registration. Its synthetic reporter prints a message so the verifier can observe it; use an appropriate private sink in a real application.

## Direct PSR-3 logging

`Handler\LogThrowableHandler` is an opt-in 2.x adapter. Use a matching checkout or release containing the class; older installed versions may not provide it. Its constructor accepts an application `LoggerInterface`, a string level defaulting to `LogLevel::ERROR`, and `array<array-key, mixed>` context.

Each `handleThrowable()` call invokes the logger's `log()` once with exactly `getMessage()`, including an empty message. The original throwable always occupies `context['exception']`, overriding a caller entry without mutating the caller's array. Other context values retain their identity and types; context-key order is not a contract. The level is forwarded unchanged, with no inference from exception codes, PHP severity or HTTP status. The logger decides whether a level is supported. Its identical failure propagates without retry or output from this adapter.

Construct the reporter and process handler explicitly with the application's logger:

```php
$reporter = new CoiSA\ErrorHandler\Handler\LogThrowableHandler($logger);

$process = new CoiSA\ErrorHandler\ErrorHandler($reporter);

$process->handleThrowable($failure);
```

This does not install callbacks, add a factory or change aggregate discovery. A logger alone in the legacy parent container does not attach this reporter. Read [explicit process composition](container.md#explicit-process-logging) before using container bindings. A private logger is not a public HTTP body renderer; retain the separate [application HTTP response boundary](http.md).

The [complete logging example](https://github.com/coisa/error-handler/blob/master/examples/logging.php) uses an in-memory recording sink. It verifies the message, level and reserved context policy without a real transport. The existing `LogErrorEventListener` retains its separate event-string behavior below.

## Conditional reporting

`new FilteringThrowableHandler($throwableHandler, $predicate)` wraps an explicitly supplied `ThrowableHandlerInterface` reporter. The required `callable(Throwable): bool` predicate runs once per invocation. `true` forwards the exact original object once; `false` skips only that delegate. Repeated calls with the same object remain separate deliveries. The adapter adds no output, callback registration, recursion guard or default filtering policy.

The predicate must return a boolean: truthy/false-like non-booleans raise `TypeError`. Predicate and delegate failures propagate unchanged, without retry. To invert a condition, supply a predicate that returns its boolean negation. A skipped branch does not recover an operation, change PHP suppression or imply a successful HTTP response; a failure still stops later aggregate reporters.

The [filtering example](https://github.com/coisa/error-handler/blob/master/examples/filtering.php) routes synthetic PHP deprecations to a selected branch while keeping the general branch. Inspect `ErrorException::getSeverity()` for PHP severity; arbitrary exception codes do not carry that meaning. Use a callable directly for one-off application logic, and the decorator when wrapping an existing reporter or aggregate. This is an additive, opt-in 2.x API: use a source version containing it; a pending minor Changeset is not a published release.

## Best-effort reporting

`new Handler\BestEffortThrowableHandlerAggregate($first, $second, ...)` is an explicit alternative to the existing fail-fast `ThrowableHandlerAggregate`. It takes a fixed constructor list of `ThrowableHandlerInterface` reporters. Construction invokes nothing and installs no callbacks. Use a checkout/release containing these new classes; a pending minor Changeset does not make them available in older 2.x installations.

Each `handleThrowable($original)` invocation attempts every constructor slot once, in order, with the identical original object. A reporter's `Throwable` failure is retained while later slots run. Duplicate slots remain deliberate separate attempts. Zero reporters and all-success passes return normally. If any reporter fails, including partial success, one `Exception\AggregateReportingException` escapes after all attempts.

The exception's `getOriginalThrowable()` and inherited `getPrevious()` return the original diagnostic. `getFailures()` returns a nonempty, ordered array of the identical reporter failure objects, including repeated objects. Its constructor requires the original, one failure and optional remaining failures. The summary message is fixed; the stored failures, previous cause and exception string still contain private diagnostics. They are not a safe public HTTP body.

The [complete synthetic example](https://github.com/coisa/error-handler/blob/master/examples/best-effort.php) catches that exception and records both the original and all failures in memory. An application must choose its own terminal handling and durable sink. This adapter does not retry, deduplicate, add a reentry guard or suppress reporter failures. Application-created recursion remains application-owned. Process termination or an uncatchable engine failure can prevent later attempts; it cannot be collected as an ordinary Throwable.

The original aggregate, default container composition, `ErrorHandler` constructor and native lifecycle are unchanged. In particular, registered exception/shutdown behavior gains no new delivery or exit guarantee. With the legacy HTTP capture factory, aggregate failure propagates through its existing cleanup path; best-effort reporting does not create a fallback response or redaction policy. See [explicit container composition](container.md) and [HTTP boundaries](http.md).

## Native callbacks

`ErrorHandlerInterface` defines only `register()` and `unregister()`. `ErrorHandler` also implements throwable, PHP-error and shutdown handling.

For a whole process, construct one handler, call `register()` at application bootstrap, and leave it registered for uncaught exceptions. For a recoverable operation, catch/report failures in that operation and call `unregister()` in `finally`. PHP executes an unwinding `finally` before invoking the uncaught-exception callback: unregistering there can prevent that handler from seeing the uncaught exception.

Registration is idempotent while that instance is registered. Unregistering restores the prior native error and exception callbacks. Use distinct objects for nested scopes, unwind in reverse order, and never share a scoped handler across concurrent fibers/requests as if callbacks were local state.

PHP provides no removal API for registered shutdown functions. Unregistering marks this handler inactive; its queued callback remains. Repeated register/unregister cycles enqueue more callbacks, so do not assume this is a bounded long-running-worker scope manager.

The [process example](https://github.com/coisa/error-handler/blob/master/examples/process.php) and [bounded verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md) cover warnings, scoped restoration, uncaught exceptions, custom shutdown and a native fatal error.

## PHP errors and shutdown

`ThrowErrorExceptionPhpErrorHandler` checks `error_reporting() & $code`, then throws the library's `Exception\ErrorException`. Disabled/suppressed levels are ignored. `ErrorHandler::handlePhpError()` catches `\ErrorException` and delegates it to the reporter; a reported warning can therefore continue.

### Callable PHP-error policy

`Handler\CallablePhpErrorHandler($callback)` is an opt-in additive API: use a checkout/release containing it, not an older installation based only on its 2.x constraint. It invokes the callback once with `(int $code, string $message, string $filename, int $line)` unchanged. It adds no mask check, conversion, output or registration; those choices belong to the callback. Callback failure propagates unchanged. Through `ErrorHandler`, a thrown `\ErrorException` follows the existing reporting boundary; other failures propagate.

The interface returns `void`, so every callback result, including false/null, is ignored. Returning false here does not delegate to PHP's native error handler. The obsolete fifth native errcontext argument is not part of this four-argument contract. To preserve normal suppression/conversion, explicitly check `error_reporting() & $code` and throw the desired ErrorException in the callback, as the [complete example](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php) does.

Inject this wrapper as the second argument: `new ErrorHandler($reporter, new CallablePhpErrorHandler($callback))`. The 2.x constructor still requires interface instances; accepting bare callables in its three positions remains [major-only work](https://github.com/coisa/error-handler/issues/70). Null retains the existing defaults. No automatic factory or default container discovery is added.

`ErrorException::fromPhpError()` preserves message, code, severity, location and the optional previous throwable. `CallableShutdownHandler` adapts an application-owned zero-argument callback.

`PhpLastErrorShutdownHandler` inspects `error_get_last()` and forwards E_ERROR, E_PARSE, E_CORE_ERROR, E_CORE_WARNING, E_COMPILE_ERROR and E_COMPILE_WARNING to its PHP-error handler. The default outer ErrorHandler reports a converted fatal. This observes a failure; it does not restart or recover PHP.

The 2.x registered exception callback is the same `handleThrowable()` method used for direct calls. It does not explicitly call `exit(1)`. In the verified CLI fixture, a successfully reported uncaught throwable exits 0, while the native compile fatal exits 255. Applications requiring a specific exit policy must own that policy; do not infer success from reporting or promise recovery after a reporter/shutdown callback itself fails.

## Aggregation and every built-in adapter

| Adapter | Existing behavior |
| --- | --- |
| `CallableThrowableHandler` | Calls one configured reporter with the throwable. |
| `CallablePhpErrorHandler` | Calls an explicit four-argument PHP-error callback once, ignoring its result; mask/conversion policy belongs to the callback. |
| `LogThrowableHandler` | Explicit PSR-3 adapter: logs the exact message and original exception once; logger failures propagate. |
| `FilteringThrowableHandler` | Evaluates an explicit boolean predicate once; matches forward the same throwable once, nonmatches skip its delegate, failures propagate. |
| `ThrowableHandlerAggregate` | Calls constructor reporters, then attached reporters, in order. An exception stops later deliveries and propagates. There is no best-effort/retry contract. |
| `BestEffortThrowableHandlerAggregate` | Opt-in fixed reporter list: attempts every slot, then throws one AggregateReportingException containing the original and all ordered reporter failures. Zero/all-success passes return normally. |
| `ThrowErrorExceptionPhpErrorHandler` | Converts enabled PHP errors to the library ErrorException. |
| `CallableShutdownHandler` | Calls a configured zero-argument callback. |
| `PhpLastErrorShutdownHandler` | Forwards a matching last fatal/compile/core error; otherwise does nothing. |
| `DispatchThrowableHandler` | Dispatches the original throwable through application-owned PSR-14. |
| `DispatchErrorEventThrowableHandler` | Dispatches a new `ErrorEvent` wrapping the throwable. |

The [adapter example](https://github.com/coisa/error-handler/blob/master/examples/handlers.php) verifies order, fail-fast behavior, error conversion/suppression and callable shutdown. Fatal tests belong only in the bounded verifier.

## Events and logging

`ErrorEvent` implements `ErrorEventInterface`; `getThrowable()` returns the original throwable and its string form is that throwable's string form.

`ThrowableListenerProvider` yields its configured/attached listeners for `Throwable` events. `ErrorEventListenerProvider` filters for the concrete `ErrorEvent` class. Neither is an event dispatcher.

`ThrowableCallableListener` and `ErrorEventCallableListener` invoke their callback and return the original event. `LogErrorEventListener` calls an application PSR-3 logger with the event string, at ERROR by default or a supplied level. It does not itself provide a logger transport or add an exception context array.

The [complete event/logging example](https://github.com/coisa/error-handler/blob/master/examples/events.php) uses an application-installed Phly dispatcher, both providers and callable listeners, plus an in-memory PSR-3 logger. Raw and wrapped dispatch are separate deliveries; configure both only when both are intended.

For explicit public JSON responses, use the separate [pure JSON renderer and reporting decorator](http.md#opt-in-json-responses-and-explicit-reporting). Diagnostic reporters retain their void contract and private-output responsibility; this opt-in composition changes no process lifecycle or existing middleware/default behavior.

## Extend through the existing contracts

The core `ErrorHandler`, built-in handlers and HTTP adapters are final. Compose them, adapt callables where supported, or implement the relevant interface in the application. A custom reporter does not need to implement callback registration or HTTP rendering.

| Extension point | Required operation | Where to supply it |
| --- | --- | --- |
| [ThrowableHandlerInterface](../src/Handler/ThrowableHandlerInterface.php) | `handleThrowable(\Throwable): void` | First `ErrorHandler` constructor argument, an aggregate slot or an explicit reporter decorator |
| [PhpErrorHandlerInterface](../src/Handler/PhpErrorHandlerInterface.php) | `handlePhpError(int, string, string, int): void` | Second constructor argument; owns PHP-error conversion/mask policy |
| [ShutdownHandlerInterface](../src/Handler/ShutdownHandlerInterface.php) | `handleShutdown(): void` | Third constructor argument; owns shutdown observation |
| [ErrorHandlerInterface](../src/ErrorHandlerInterface.php) | `register(): void` and `unregister(): void` | Middleware lifecycle argument; this interface alone has no reporting method |
| [ThrowableStreamFactoryInterface](../src/Http/Message/ThrowableStreamFactoryInterface.php) | `createStreamFromThrowable(\Throwable): StreamInterface` | Built-in response factory's stream dependency |
| [ThrowableResponseFactoryInterface](../src/Http/Message/ThrowableResponseFactoryInterface.php) | `createResponseFromThrowable(\Throwable): ResponseInterface` | Middleware response dependency; owns reporting policy for its caught throwable in 2.x |

The HTTP return types above are PSR-7 interfaces. Container factories consume the same contracts; select overrides under the IDs they actually request, as shown in [the alias and factory map](container.md#factory-map-and-scopes). There is no library-wide static accessor or automatic discovery of arbitrary reporter classes.

For events, implement or reuse an application [PSR-14 dispatcher](https://www.php-fig.org/psr/psr-14/) and select its listeners. The library's listener providers return listeners; they do not execute them. For logging, supply a [PSR-3 logger](https://www.php-fig.org/psr/psr-3/) with a configured private destination.

## Troubleshoot the reporting path

| Observation | Check next |
| --- | --- |
| Calling `handleThrowable()` reports but the program keeps running | This is the direct-call contract. Put recovery or termination in the application; reporting alone does not select either. |
| A warning is absent from diagnostics | Check `error_reporting()` and suppression, then whether the handler is registered or called explicitly. A custom PHP-error adapter owns its own mask policy. |
| An uncaught exception is absent after a scoped operation | Check whether an unwinding `finally` unregistered the handler before PHP invoked the exception callback. Use the [process recipe](examples.md#process-callbacks-and-scoped-restoration) through its bounded verifier. |
| Later reporters are never reached | The default aggregate stops on the first reporter failure. Inspect that failure or explicitly select [best-effort reporting](#best-effort-reporting) and its terminal policy. |

For missing services or unexpected shared instances, use [container troubleshooting](container.md#troubleshoot-service-resolution). For leaked response text or repeated HTTP delivery, use [HTTP troubleshooting](http.md#troubleshoot-http-composition).

Continue with [container composition](container.md), [HTTP composition](http.md), and the [executable cookbook](https://github.com/coisa/error-handler/blob/master/examples/README.md).
