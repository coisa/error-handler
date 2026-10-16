# HTTP composition in 2.x

The library implements PSR-15 middleware and consumes application-owned PSR-17 factories. It returns a PSR-7 response; the application/server owns request creation, routing and response emission. Composer's PSR interfaces are not concrete HTTP factories or an emitter.

Run the [HTTP example](https://github.com/coisa/error-handler/blob/master/examples/http.php) through the [bounded verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md). It supplies Nyholm's Psr17Factory, a synthetic downstream RequestHandlerInterface and application callbacks. No real server is needed.

## Current middleware contract

`new ErrorHandlerMiddleware($errors, $responses)` takes `ErrorHandlerInterface` and `ThrowableResponseFactoryInterface`. `ErrorHandlerInterface` is a lifecycle contract only; no intersection with `ThrowableHandlerInterface` is required by the 2.x constructor.

Processing registers the handler, invokes the downstream request handler, catches Throwable and asks the response factory for a response, then unregisters in `finally`. A normal response passes through. Response-factory failure propagates after unregistering restores surrounding callbacks.

The middleware does not independently call `handleThrowable()` in that catch. With the built-in response/stream factories, reporting happens during stream rendering. Adding a separate diagnostic call before that renderer can produce duplicate delivery. Custom response factories own their reporting policy.

Use a distinct core handler for each nested scope and retain the process handler separately. The default container can cache/reuse a handler for both; factory discovery does not provide independent request lifetimes. Native callbacks remain process-wide, including under fibers or concurrent workers.

## Existing response and output capture

The default composition is:

```php
$streams = new CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory($messages, $reporter);

$responses = new CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory($streams, $messages);

$middleware = new CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware($errors, $responses);
```

Here `$messages` implements both PSR-17 stream/response factory contracts.

`ThrowableResponseFactory` passes the throwable message unchanged as the HTTP reason phrase when it contains only legal status-line bytes: horizontal tabs, spaces, visible ASCII or opaque bytes from `0x80` through `0xFF`. If it contains CR, LF, NUL, DEL or another forbidden control byte, the factory omits the reason argument so the PSR-17 implementation can select its default instead. An empty message is still passed explicitly, preserving the existing implementation-specific behavior (Nyholm retains an empty reason). Verify that the selected checkout/release contains the [reason-phrase repair](https://github.com/coisa/error-handler/issues/89); this is not guaranteed by every 2.x installation.

The factory uses integer codes from 400 through 500 inclusive as status, and 500 otherwise. String codes, including PDO SQLSTATE and numeric-looking strings, fall back to 500. This existing policy is not a general application error-to-HTTP mapping; choose application-owned mapping for domain/database errors. Reason validation changes neither the original throwable sent to the stream/reporting path nor its body policy. It prevents an invalid status line; it does not redact valid diagnostic text from the response.

`ThrowableStreamFactory` starts an output buffer, invokes its configured reporter, then uses the captured text. If that text is empty or false-like (including the string `"0"`), it falls back to the throwable's string representation, including diagnostic details and stack information. A private logger that prints nothing therefore does not produce a safe generic body.

The executable modes verify these actual paths:

| Mode | Observed result |
| --- | --- |
| `capture` | Synthetic public output becomes the body; the example code/message become status/reason. |
| `empty` | No reporter output: body falls back to exception text. |
| `zero` | Reporter output `"0"` also falls back to exception text. |
| `success` | Normal downstream response, no throwable report. |
| `renderer-failure` | Application renderer throws; callbacks are restored. |
| `reporter-failure` | Built-in capture reporter throws; its removable capture buffer is discarded and callbacks are restored. |

On reporter or capture failure, the stream factory attempts to discard removable buffers above its entry level. If cleanup returns, it rethrows the same primary failure. It does not attempt to close buffers at or below the entry level. A throwing output callback does not stop cleanup when PHP has already removed its buffer; cleanup then continues through the remaining reachable removable scopes. It stops at a non-removable buffer, a failed close or no decrease in buffer level. Buffers can remain in those cases. Callback side effects cannot be undone: an output handler that calls `exit()`/`die()` or triggers an engine fatal can terminate execution before the caller receives the primary failure. This best-effort cleanup cannot restore control in those cases. Reporters must still balance their own buffers, including on success: successful unbalanced nested capture retains its existing 2.x behavior.

## A safe application response

An explicitly configured `BestEffortThrowableHandlerAggregate` attempts all its reporter slots, then throws if any failed. The existing capture factory propagates that aggregate failure through the cleanup boundary above; it does not produce a successful fallback response. Its fixed summary does not make its original cause, failures or string representation public-safe. See [the terminal policy](api.md#best-effort-reporting) before composing it into an HTTP reporter.

The `safe` mode uses an application implementation of `ThrowableResponseFactoryInterface`. It calls a private, non-output diagnostic reporter once, then creates a fixed 500 response with `Internal Server Error` through the supplied PSR-17 implementation. It does not copy exception messages, stack traces or database codes into the public body or reason phrase.

This is an explicit application adapter. The default renderer remains the capture/fallback adapter described above. The opt-in JSON factory below is a separate additive API; verify the selected source or installed release contains it. Neither JSON nor content negotiation becomes a default.

An explicit `FilteringThrowableHandler` may select the private diagnostic branch in this application adapter. Both match and nonmatch still lead to its fixed public response; filtering does not select status, body or reason. Predicate/delegate failure propagates after middleware restores callbacks. Do not use a silent nonmatch as redaction inside the built-in capture adapter: no output still falls back to the original diagnostic text. Filtering starts after PHP-error conversion and preserves the existing reporting mask/suppression rules.

## Opt-in JSON responses and explicit reporting

`Http\Message\JsonThrowableResponseFactory` accepts a PSR-17 response factory and stream factory. It returns a fresh response/body with `Content-Type: application/json` and the exact public body `{"error":"Request failed"}`. Integer throwable codes from 400 through 599 are preserved; strings, including numeric-looking codes and SQLSTATE, and all other integers use 500. The PSR-17 implementation supplies its default reason phrase; the throwable message is never passed as that reason.

This renderer returns data directly. It does not invoke a diagnostic reporter, print, capture output, register callbacks, emit the response or select a representation from request globals. Its status range belongs to this new opt-in class; the legacy factory's 400–500 mapping, throwable reason phrase and output-capture defaults remain unchanged.

The 2.x middleware does not report a caught downstream Throwable before rendering. Compose `Http\Message\ReportingThrowableResponseFactory` with a private, non-output reporter and the pure JSON renderer:

```php
$errors = new CoiSA\ErrorHandler\ErrorHandler($reporter);

$json = new CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory($messages, $messages);

$responses = new CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory($reporter, $json);

$middleware = new CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware($errors, $responses);
```

The decorator calls its reporter once with the original Throwable, then calls its renderer once with the same object and returns that exact response. A reporter failure prevents rendering; reporter and renderer failures propagate unchanged, with middleware unregistering in `finally`. The decorator does not silence a reporter that prints. Use a private sink; diagnostic output remains that collaborator's responsibility.

### Explicit development details

The optional third JSON constructor argument is `bool $debug = false`. Enable it only from trusted application configuration, for example `new JsonThrowableResponseFactory($messages, $messages, true)` in a development-only composition. The library does not inspect environment names, request headers, remote addresses or globals to enable diagnostics. Omitting the argument keeps the generic public body.

Debug adds `exception` containing `class`, `message`, `file`, `line` and `trace`, and sets `Cache-Control: no-store`. Trace frames retain only the native `file`, `line`, `class`, `type` and `function` keys that are present. Arguments, objects, previous-exception chains and arbitrary properties are excluded; the Throwable is not serialized. Debug intentionally exposes the chosen message and paths, which can contain sensitive information. It is not a complete redaction policy.

Debug encoding replaces invalid UTF-8 bytes in messages, paths and trace metadata with U+FFFD. It does not retry or report again. PHP 7.4 debug use needs the optional JSON extension and an available `json_encode`; the constructor rejects a missing encoder when debug is selected, before a Throwable is being handled. PHP 8 includes JSON. Generic rendering needs no encoder, and the core package does not acquire an extension requirement.

Using JSON directly in the 2.x middleware omits explicit reporting of that caught Throwable unless the application supplies another reporting composition. Wrapping the legacy capture renderer would instead add a second report because capture already invokes its reporter. Use the decorator with a pure renderer. In the proposed major's report-before-render middleware, inject the pure JSON factory directly and omit this decorator to avoid duplicate delivery; that major is not the published 2.x contract.

The [complete JSON example](https://github.com/coisa/error-handler/blob/master/examples/http-json.php) preserves a synthetic 503 and records the original privately once in both `public` and explicitly selected `debug` modes. It uses application-provided PSR-17 factories without application-side buffers or an emitter. The CLI mode represents trusted application configuration, not request input. Run both through the existing bounded verifier.

### JSON through an Interop service provider

For an application using PHP 8.3+ and FastForward Container `^1.9` as a runtime dependency, select `ServiceProvider\JsonErrorHandlerServiceProvider` with the application's PSR-17 and private reporter definitions:

```php
$services = FastForward\Container\container(
    CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider::class,
    $application,
);

$middlewareFactory = $services->get(
    CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory::class,
);

$middleware = $middlewareFactory($services);
```

The profile selects the reporting decorator automatically for this explicit 2.x composition; the pure concrete remains independently available. Invoking the middleware factory creates a new lifecycle handler rather than reusing the cached process handler. Discovery is inert, public JSON is the default, and a real bool constructor argument enables debug only by trusted application choice. The core and legacy container still support PHP 7.4.

Read the [provider dependencies, Config values and override precedence](container.md#optional-interop-and-fastforward-providers) and the [complete executable provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php). Run the optional provider scenarios with `php examples/verify.php --with-fast-forward` on PHP 8.3+; the existing verifier keeps their callback installation in bounded children. Applications continue to own emission and private reporter output.

Keep emission after response creation in the application's existing PSR-7 emitter/server integration. This library does not call header(), echo a response to a client or select an emitter package. Output captured by the diagnostic adapter is an input to a response body, not HTTP emission.

See [core callback behavior](api.md) and [container dependencies](container.md) before integrating the middleware into a process that already has handlers.
