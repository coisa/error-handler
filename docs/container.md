# Container integration in 2.x

2.x retains `Container\ErrorHandlerContainer`, `Container\ConfigProvider`, factories under `Container\Factory` and Composer's `extra.laminas.config-provider` metadata. These legacy routes do not require FastForward. The [optional Interop providers](#optional-interop-and-fastforward-providers) below are additive and require an application using PHP 8.3+; they do not adopt the proposed major's middleware or defaults.

The runtime PSR-11 dependency is the interface package, not another container implementation. The existing typed identifiers support PSR-11 `^1.1 || ^2.0`; 1.0 is incompatible. Published versions predating the [runtime metadata correction](https://github.com/coisa/error-handler/issues/13) need PSR-11 explicitly installed by the application. The [PHP 7.4 alias repair](https://github.com/coisa/error-handler/issues/40) also needs to be present for normal minimum-runtime alias resolution.

## Choose a composition boundary

For the built-in container, supply an optional application PSR-11 parent:

```php
$services = new CoiSA\ErrorHandler\Container\ErrorHandlerContainer($application);

$errors = $services->get(CoiSA\ErrorHandler\ErrorHandler::class);
```

The parent takes priority for IDs it reports as present. Otherwise library aliases and factories resolve services. Instances, including alias results and handlers, are cached. Unknown IDs throw the library's PSR-11 `NotFoundException`; construction errors are wrapped in `ContainerException`. A failed parent service is not a signal to silently substitute a different reporter.

For source/releases containing the [container-cause repair](https://github.com/coisa/error-handler/issues/90), both `ContainerException::createFromThrowable()` and `NotFoundException::createFromThrowable()` keep the exact original throwable as `getPrevious()`, including its earlier cause chain. Integer codes, including zero and negative values, are copied unchanged. String codes such as PDO SQLSTATE `HY000` or `23000` use `0` on the wrapper; the original string remains on the previous throwable. Numeric-looking strings are not converted to integers. Nested library factory/alias wrappers retain that original diagnostic in their cause chain. Direct parent delegation keeps its existing behavior.

Alternatively a `ConfigProvider`-aware application can consume its maps:

```php
$provider = new CoiSA\ErrorHandler\Container\ConfigProvider();
$configuration = $provider();
$dependencies = $configuration['dependencies'];
```

`getDependencies()` returns aliases and factories; `getAliases()` and `getFactories()` expose each map. Laminas discovery reads this metadata; construction/discovery does not call `register()`.

The [runnable container example](https://github.com/coisa/error-handler/blob/master/examples/container.php) demonstrates the built-in parent route and native Laminas ServiceManager configuration. ServiceManager is an application dependency here, with a version appropriate for that application's PHP; it is not required by the library at runtime.

## Reporter composition and external dependencies

The default `ThrowableHandlerInterface` alias resolves an initially empty `ThrowableHandlerAggregate`. Empty means silent: explicitly configure a reporter before expecting diagnostics.

`ThrowableHandlerAggregateFactory` attaches an available `CallableThrowableHandler`. When `EventDispatcherInterface` is present, it also attaches the available raw and wrapped dispatch handlers. Each configured route receives its own delivery. Provide the dispatcher as an application service; the PSR-14 interface alone cannot dispatch anything.

HTTP composition additionally requires application `ResponseFactoryInterface` and `StreamFactoryInterface` implementations. A PSR-17 implementation such as Nyholm's Psr17Factory can satisfy both. Missing required implementations are container construction errors; neither `ConfigProvider` nor PSR interfaces provide implementations automatically.

## Explicit process logging

`Handler\LogThrowableHandler` is constructed explicitly; it has no library factory, alias or automatic aggregate membership. Providing `LoggerInterface` alone leaves the existing default aggregate unchanged. With an application logger, construct the process handler directly:

```php
$reporter = new CoiSA\ErrorHandler\Handler\LogThrowableHandler($logger);

$process = new CoiSA\ErrorHandler\ErrorHandler($reporter);
```

An application may bind that process object under `ErrorHandler::class` in its parent container. Keep any HTTP renderer/lifecycle composition explicit and separate. Binding any reporter globally under `ThrowableHandlerInterface` also selects the legacy captured-output renderer's reporter. A silent reporter, including private logging or a filtering predicate that returns `false`, leaves empty captured output and causes fallback to diagnostic exception text. Select process reporting and public response construction explicitly; neither silence nor filtering establishes a safe public body. See [the logging contract](api.md#direct-psr-3-logging), [conditional reporting](api.md#conditional-reporting) and [HTTP composition](http.md).

## Factory map and scopes

`BestEffortThrowableHandlerAggregate` is also explicit: construct it with the desired reporters and inject it into an application-owned `ErrorHandler`. It has no factory, alias or automatic membership in the default fail-fast aggregate. Merely providing a service under its class name does not select it. A global `ThrowableHandlerInterface` override also changes the legacy capture renderer's reporter: any collected failure propagates after all slots, while all-success silent output retains diagnostic fallback. Keep process reporting and public response construction separate; see [best-effort reporting](api.md#best-effort-reporting) and [HTTP composition](http.md).

`CallablePhpErrorHandler` has no automatic factory/discovery. Construct it explicitly and pass it as the second ErrorHandler argument, or provide the instance under `PhpErrorHandlerInterface` in the application parent. The default PHP-error adapter remains unchanged when no override is supplied. Its callback owns mask/conversion policy and its return value is ignored; see [the API contract](api.md#callable-php-error-policy).

`FilteringThrowableHandler` requires an application predicate and is not discovered or attached to the default aggregate automatically. Construct it explicitly, then provide it through the application parent's `ThrowableHandlerInterface` service or attach it to an application-composed aggregate. That global service also affects legacy HTTP rendering as explained above; for process-only composition, bind the application `ErrorHandler::class` explicitly. Merely registering a service under `FilteringThrowableHandler::class` does not select it for reporting. There is no library filtering factory or implicit allow-all predicate.

All factory names below are in `CoiSA\ErrorHandler\Container\Factory`.

| Service | Factory |
| --- | --- |
| `ErrorHandler` | `ErrorHandlerFactory` |
| `Http\Middleware\ErrorHandlerMiddleware` | `ErrorHandlerMiddlewareFactory` |
| `Handler\ThrowableHandlerAggregate` | `ThrowableHandlerAggregateFactory` |
| `Handler\DispatchThrowableHandler` | `DispatchThrowableHandlerFactory` |
| `Handler\DispatchErrorEventThrowableHandler` | `DispatchErrorEventThrowableHandlerFactory` |
| `Http\Message\ThrowableStreamFactory` | `ThrowableStreamFactoryFactory` |
| `Http\Message\ThrowableResponseFactory` | `ThrowableResponseFactoryFactory` |

The remaining aliases map `ThrowableHandlerInterface`, `ThrowableStreamFactoryInterface` and `ThrowableResponseFactoryInterface` to the corresponding aggregate/stream/response class. `AliasFactory` returns the exact delegated service value.

Repeated `get(ErrorHandler::class)` returns the cached handler. Invoking a new `ErrorHandlerFactory` with the container constructs another core handler, while its dependencies can remain shared.

Invoking `ErrorHandlerMiddlewareFactory` constructs a middleware wrapper but asks the container for its handler. That handler may still be cached. For independent nested scopes, explicitly construct a distinct ErrorHandler and middleware; do not treat a fresh wrapper as proof of a fresh handler. The existing middleware factory does not isolate process and request handlers automatically.

`Http\Message\JsonThrowableResponseFactory` and `ReportingThrowableResponseFactory` are explicit opt-in services. The legacy routes do not select them. Construct them with application-owned PSR-17 factories and a private, non-output reporter, then construct a distinct middleware scope as shown in the [JSON composition](http.md#opt-in-json-responses-and-explicit-reporting), or choose the JSON Interop profile below. An application may supply the composed response factory under `ThrowableResponseFactoryInterface`, but that override does not make the legacy cached lifecycle handler a fresh request scope. Keep process and public rendering composition intentional.

## Optional Interop and FastForward providers

First verify that the selected source/release contains `ServiceProvider\ErrorHandlerServiceProvider` and `JsonErrorHandlerServiceProvider`. These optional providers use `Interop\Container\ServiceProviderInterface` and FastForward's reusable factories. Core PHP 7.4 consumers retain the legacy routes. The provider integration needs PHP 8.3+ and FastForward installed as an **application runtime dependency**:

```sh
composer require 'fast-forward/container:^1.9' 'nyholm/psr7:^1.8' --no-plugins --no-scripts
```

The library requires only the small Interop contract; FastForward is optional for library consumers and present in the library's development graph for its PHPUnit checks. Installing it only as the application's `require-dev` would leave a production `--no-dev` installation without its chosen container. Applications still own PSR-17 factories, their private `ThrowableHandlerInterface` reporter and response emission. The providers do not install an emitter or choose a logging destination.

With the application map from the [complete provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php), import `ServiceProvider\JsonErrorHandlerServiceProvider`, `ServiceProvider\ErrorHandlerServiceProvider` and `FastForward\Container\container`, then select public JSON:

```php
$services = container(
    JsonErrorHandlerServiceProvider::class,
    ErrorHandlerServiceProvider::class,
    $application,
);
```

Each provider declares only its own services; the JSON profile does not include the base provider. Register JSON **before** the base when passing them separately to `container()`: FastForward selects the first successful definition of `ThrowableResponseFactoryInterface`. Putting the base first selects legacy capture even when JSON is also present.

The JSON profile declares its pure concrete renderer and selects `ReportingThrowableResponseFactory` under `ThrowableResponseFactoryInterface`. The existing 2.x middleware therefore reports the original privately once before rendering. Generic JSON is the default; it preserves integer HTTP 400–599 codes and returns `{"error":"Request failed"}` without diagnostic text. A reporter that prints still owns that output; use one that writes no public diagnostics. The concrete JSON factory remains pure when resolved directly.

The base `ErrorHandlerServiceProvider` has no arguments. It defines lifecycle aliases, reusable scope factories and the existing capture defaults, requiring the application's reporter instead of selecting an empty aggregate. It registers neither JSON nor an implicit reporter. Merely providing PSR-17 or a logger does not choose JSON or compose reporting.

For explicit development JSON, give the profile a real bool from trusted application configuration **before** building the container:

```php
$settings = FastForward\Config\config([
    'error_handler' => ['json' => ['debug' => false]],
]);

$services = container(
    new JsonErrorHandlerServiceProvider($settings->get('error_handler.json.debug', false)),
    ErrorHandlerServiceProvider::class,
    $application,
);
```

Use `declare(strict_types=1)` in the application entrypoint, as the complete recipe does. Config supplies values; it does not hydrate this object or inject constructor scalars automatically. The bool follows PHP's normal caller-dependent typing: weak callers may coerce strings, and `(bool) 'false'` is true. Do not derive diagnostics from public request headers or naïvely cast an environment string. Debug details and `Cache-Control: no-store` follow the [JSON contract](http.md#opt-in-json-responses-and-explicit-reporting).

### Cached services and fresh HTTP scopes

`ErrorHandlerInterface` aliases the cached process `ErrorHandler`. Obtaining it or constructing the provider does not register callbacks. The Interop middleware factory lives under `ServiceProvider\Factory`, separately from the unchanged legacy factory:

```php
$middlewareFactory = $services->get(
    CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory::class,
);

$middleware = $middlewareFactory($services);

$response = $middleware->process($request, $next);
```

Each invocation reuses the existing core `Container\Factory\ErrorHandlerFactory` to create a distinct lifecycle handler and middleware wrapper. The cached process handler is not reused for that HTTP scope. Optional PHP-error/shutdown collaborators retain the existing factory's behavior and may remain shared application services. Resolving `ErrorHandlerMiddleware` directly is also supported, but that service is cached; request fresh scopes deliberately when composing nested or sequential operations.

The native lifecycle remains 2.x: unregister restores callbacks, but PHP cannot remove queued shutdown functions. Fresh objects are not a bounded worker scheduling mechanism. Keep process ownership and long-running application policy explicit.

### Required application overrides

Separate FastForward providers prioritize the first successful resolution. The simple JSON → base → application call works for the required application PSR-17/reporter services because neither library provider defines them. For a required replacement of a service the library already defines, combine the maps in one aggregate in the order **base → JSON → application**; the last definition wins inside this aggregate:

```php
$services = container(
    new FastForward\Container\ServiceProvider\AggregateServiceProvider(
        new ErrorHandlerServiceProvider(),
        new JsonErrorHandlerServiceProvider(),
        $application,
    ),
);
```

That order makes the application's renderer override deterministic. Putting a competing definition in a later, separate provider does not override the first one. FastForward can try a later candidate after a PSR container failure; a failing concrete factory may fall through to autowiring with default scalar values. This profile receives `debug` at construction to keep that choice outside service resolution, rather than reading a config service ID during construction of the concrete renderer. Other container errors retain FastForward's semantics.

These are the optional **2.x** providers: they preserve legacy classes and compose reporting for middleware that does not report first. A future report-before-render major must select the pure renderer directly; do not carry the reporting decorator into that middleware path.

Applications decide when a resolved process handler calls `register()`. For HTTP registration/restoration, rendering and emission boundaries, see [HTTP](http.md).
