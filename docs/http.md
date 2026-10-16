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

This is an explicit application adapter. The built-in renderer remains the capture/fallback adapter described above. A proposed future pure renderer, JSON API or content negotiation policy is not an existing 2.x default.

An explicit `FilteringThrowableHandler` may select the private diagnostic branch in this application adapter. Both match and nonmatch still lead to its fixed public response; filtering does not select status, body or reason. Predicate/delegate failure propagates after middleware restores callbacks. Do not use a silent nonmatch as redaction inside the built-in capture adapter: no output still falls back to the original diagnostic text. Filtering starts after PHP-error conversion and preserves the existing reporting mask/suppression rules.

Keep emission after response creation in the application's existing PSR-7 emitter/server integration. This library does not call header(), echo a response to a client or select an emitter package. Output captured by the diagnostic adapter is an input to a response body, not HTTP emission.

See [core callback behavior](api.md) and [container dependencies](container.md) before integrating the middleware into a process that already has handlers.
