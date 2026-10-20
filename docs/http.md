# HTTP composition in 2.x

The library implements PSR-15 middleware and consumes application-owned PSR-17 factories. It returns a PSR-7 response; the application/server owns request creation, routing and response emission. Composer's PSR interfaces are not concrete HTTP factories or an emitter.

Run the [HTTP example](https://github.com/coisa/error-handler/blob/master/examples/http.php) through the [bounded verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md). It supplies Nyholm's Psr17Factory, a synthetic downstream RequestHandlerInterface and application callbacks. No real server is needed.

## Prepare the application boundary

Read [the setup guide](../examples/README.md#application-dependencies) for a compatible application dependency graph. [PSR-15](https://www.php-fig.org/psr/psr-15/) defines middleware/request-handler contracts; [PSR-17](https://www.php-fig.org/psr/psr-17/) defines HTTP message factories. Their interface packages do not create a concrete request, response body or network server for the application.

For the two legacy compositions demonstrated here, identify:

- A private reporter implementing `ThrowableHandlerInterface` and a distinct lifecycle handler for this scope.
- Application PSR-17 `ResponseFactoryInterface` and `StreamFactoryInterface` implementations; the recipe uses one Nyholm object for both.
- A downstream PSR-15 `RequestHandlerInterface` and the application's existing response emitter/server integration.
- The public status/body/reason policy and the action to take if reporting or response construction fails.

A custom `ThrowableResponseFactoryInterface` implementation may create its PSR-7 `ResponseInterface` differently. PSR-17 factories are dependencies of the demonstrated implementations, rather than a requirement of that library interface.

| Intended response policy | Composition to choose | Reporting owner |
| --- | --- | --- |
| Fixed public error response with private diagnostics | Application `ThrowableResponseFactoryInterface`, as in the complete recipe's `safe` mode | The application response factory reports once, then creates the fixed response. |
| Deliberate use of existing captured reporter output | Built-in `ThrowableResponseFactory` with `ThrowableStreamFactory` | The stream factory invokes its reporter while capturing output; empty/false-like output exposes diagnostic fallback. |

Select this policy explicitly before using the [default container aliases](container.md#factory-map-and-scopes). A private reporter alone does not make the captured-output composition suitable for public responses.

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

If the reporter closes capture and returns normally, the factory uses that same diagnostic fallback. It does not read or close a caller-owned buffer at or below the entry depth, or invoke that buffer's output callback. With no caller buffer, the missing capture also reaches fallback without calling `ob_get_clean()`. This ownership boundary cannot undo a reporter's own writes, flushes or buffer changes; reporters must still balance their buffers.

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

Read the [complete HTTP recipe](examples.md#http-responses-output-capture-and-a-safe-application-adapter) for its imports, synthetic request handler, application response adapter and assertions. Through the bounded verifier, the safe case checks one private report, the fixed 500 status/body and restored callbacks. A reporter failure propagates before the fixed response is created; PSR-17 failures propagate too. The application still needs a terminal failure policy for its chosen server/runtime.

This is an explicit application adapter. The default renderer remains the capture/fallback adapter described above. The opt-in JSON factory below was published in 2.5.0; select `coisa/error-handler:^2.5` and verify the installed source. Neither JSON nor content negotiation becomes a default.

An explicit `FilteringThrowableHandler` may select the private diagnostic branch in this application adapter. Both match and nonmatch still lead to its fixed public response; filtering does not select status, body or reason. Predicate/delegate failure propagates after middleware restores callbacks. Do not use a silent nonmatch as redaction inside the built-in capture adapter: no output still falls back to the original diagnostic text. Filtering starts after PHP-error conversion and preserves the existing reporting mask/suppression rules.

## Opt-in JSON responses and explicit reporting

JSON, its reporting decorator and the optional Interop profiles are available from published 2.5.0. For an authorized application installation selecting these routes, use:

```sh
composer require 'coisa/error-handler:^2.5' --no-plugins --no-scripts
```

The common reporting and legacy integration baseline remains `^2.4`.

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

For an application using PHP 8.3+ and FastForward Container `^1.9` as a runtime dependency, register `ServiceProvider\JsonErrorHandlerServiceProvider` before the separate base provider, followed by the application's PSR-17 and private reporter definitions:

```php
$services = FastForward\Container\container(
    CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider::class,
    CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider::class,
    $application,
);

$middlewareFactory = $services->get(
    CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory::class,
);

$middleware = $middlewareFactory($services);
```

Each provider declares only its own services. The JSON profile selects the reporting decorator for this explicit 2.x composition; the base supplies lifecycle and fresh-scope factories. JSON must come first in this separate-provider call so its response alias wins over legacy capture. The pure concrete remains independently available. Invoking the middleware factory creates a new lifecycle handler rather than reusing the cached process handler. Discovery is inert, public JSON is the default, and a real bool constructor argument enables debug only by trusted application choice. The core and legacy container still support PHP 7.4.

Read the [provider dependencies, Config values and override precedence](container.md#optional-interop-and-fastforward-providers) and the [complete executable provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php). Run the optional provider scenarios with `php examples/verify.php --with-fast-forward` on PHP 8.3+; the existing verifier keeps their callback installation in bounded children. Applications continue to own emission and private reporter output.

## Opt-in development HTML with Whoops

`Http\Message\WhoopsThrowableResponseFactory` was published in 2.5.0. Applications selecting this development HTML route need `coisa/error-handler:^2.5`. Install optional `filp/whoops:^2.18.7` in that application's development dependencies. The library suggests Whoops and uses it for development tests; core/container installations do not require it. Loading the adapter class without Whoops works; constructing it without the dependency raises `LogicException` with installation guidance.

The minimum Whoops 2.18.7 includes the upstream PHP 8.5 deprecation fix and native virtual-source inspection repair. Whoops preserves frames created through STDIN (`Standard input code`) and `php -r` (`Command line code`) without trying to read those names as files. Composer rejects older Whoops versions through an optional conflict constraint; it does not install Whoops unless the application requests it. This requirement neither adds Whoops to core runtime dependencies nor suppresses legitimate application warnings.

The constructor takes `ResponseFactoryInterface`, `StreamFactoryInterface` and a required `callable(): Whoops\Handler\PrettyPageHandler`. The callable supplies the configured page for each render and is not invoked during construction. It may create a new page or return a configured page reused for sequential rendering. Whoops replaces the page's current exception, inspector and generated template data; configuration stays in place. A reused page retains its latest diagnostic until the next render. This is not a concurrency or arbitrary-subclass guarantee. The adapter creates its own fresh Run, sets `handleUnconditionally(true)`, `allowQuit(false)`, `writeToOutput(false)` and `sendHttpCode(false)`, and never calls `register()`.

HTML comes from Whoops' public string-returning path. Its internal buffering belongs to Whoops; CoiSA adds no reporter-output capture. The adapter returns a new PSR body with `text/html; charset=UTF-8` and `Cache-Control: no-store`. It preserves integer codes 400–599, otherwise uses 500, and omits the reason argument so PSR-17 supplies its default. The original diagnostic code shown by Whoops is not a separate HTTP status promise; the Throwable is neither wrapped nor changed.

Reuse `ReportingThrowableResponseFactory($reporter, $html)` in the 2.x middleware. The private reporter runs once before rendering; its failure prevents page lookup. Failures escaping the manual page callback, Whoops or HTTP collaborators propagate without adapter retry, fallback or another report. Returning an incompatible page produces `TypeError`. These controls do not suppress arbitrary output, callbacks or process termination deliberately performed by application callbacks/subclasses.

The renderer leaves the original Throwable and its trace unchanged. The 2.x reporting decorator still reports the original once before rendering, and enabled application PHP warnings still reach their configured reporter. No warning suppression, fabricated filename or trace rewriting is supplied. The [native source regression](testing.md#optional-whoops-html-adapter) checks STDIN, `php -r` and real files separately.

The [complete HTML recipe](https://github.com/coisa/error-handler/blob/master/examples/http-whoops.php) starts with generic JSON. Its trusted `development` mode selects Whoops in an explicitly chosen HTML pipeline; `public` mode never constructs a page and can run without Whoops. The mode represents application configuration, not a request parameter. There is no additional debug flag on the HTML class: choosing this diagnostic renderer is the explicit opt-in.

`hideSuperglobalKey()` hides selected fields, not every occurrence of a secret. Default Whoops pages expose diagnostic data and ambient superglobals; messages, traces, source excerpts, causes and other tables may remain sensitive. The recipe's three hidden keys are examples, not a universal policy. HTML does not have the JSON adapter's metadata-only trace guarantee. Keep development access and broader data policy with the application; do not use this as a redacted production page.

This factory receives only Throwable. It does not negotiate Accept, infer a browser, inspect request globals to enable debug or retain a current request. Existing application format selection may choose it after an independent trusted debug decision. No `Vary: Accept` is added; an application that negotiates must set its own Vary/cache policy. Reusable negotiation remains separate [issue 8](https://github.com/coisa/error-handler/issues/8). The proposed major receives the pure renderer directly after reporting, without the 2.x decorator; [issue 95](https://github.com/coisa/error-handler/issues/95) tracks that adaptation.

### Whoops through an Interop service provider

With application PHP 8.3+, `fast-forward/container:^1.9` installed at runtime and optional `filp/whoops:^2.18.7`, choose Whoops after the trusted development/HTML decision. Compose the independent providers in one `AggregateServiceProvider`, ordered `ErrorHandlerServiceProvider`, `WhoopsErrorHandlerServiceProvider`, application. Its last definition wins, retaining HTML and application page overrides. Supply the same application PSR-17 factories and private reporter used by the JSON profile. The Whoops map selects the 2.x reporting decorator; the base supplies `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` for a fresh HTTP scope.

The profile has no constructor arguments. It defines `PrettyPageHandler::class` through FastForward's `InvokableFactory` and resolves that cached page lazily when rendering starts. Neither provider discovery, renderer resolution nor middleware scope construction creates the default page. The [complete provider recipe](../examples/provider-whoops.php) also shows application Config preparing a customized page before composition, bound through `ServiceFactory`. Sequential renders reuse page configuration while replacing the current diagnostic, with a new Run, response and stream each time. There is no request negotiation or automatic development detection. Selected key hiding remains a limited diagnostic policy, not comprehensive redaction.

Use the documented aggregate for Whoops. Separate providers can fall back to reporting around legacy capture when Whoops is absent, reporting the same original twice instead of preserving the missing-dependency hint. The aggregate preserves that hint in the verified no-Whoops graph, but does not disable FastForward's autowiring fallback after a concrete page factory throws. The renderer propagates errors that actually escape page lookup; it does not enforce container resolution policy. See the [provider configuration and failure boundaries](container.md#development-html-profile).

Keep emission after response creation in the application's existing PSR-7 emitter/server integration. This library does not call header(), echo a response to a client or select an emitter package. Output captured by the diagnostic adapter is an input to a response body, not HTTP emission.

## Troubleshoot HTTP composition

| Observation | Check next |
| --- | --- |
| A stack trace or exception message appears in the body despite private logging | The built-in capture adapter falls back to diagnostic text for empty output or `"0"`. Select the explicit application response adapter and assert the exact public body. |
| One downstream failure reaches a reporter twice | Inspect raw/wrapped event routes, manual reporting and the response factory's policy. The 2.x middleware delegates to the response factory; adding another diagnostic call before the built-in capture renderer duplicates reporting. |
| A PDO or domain error produces an unexpected status/reason | The legacy mapping uses integer 400–500 only and can preserve a valid diagnostic message as reason. Provide application response mapping and test status, reason and body together. |
| No response reaches the client after `process()` returns | Pass the returned PSR-7 response to the application's emitter. Creating a response object does not send headers or a body. |
| Response creation throws instead of returning a fallback | Reporter and renderer failures propagate after callback restoration. Inspect the original private failure and apply the application's terminal policy. Best-effort reporting also throws when any reporter slot fails. |

See [core callback behavior](api.md) and [container dependencies](container.md) before integrating the middleware into a process that already has handlers.
