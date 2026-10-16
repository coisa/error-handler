# Existing container integration in 2.x

2.x retains `Container\ErrorHandlerContainer`, `Container\ConfigProvider`, factories under `Container\Factory` and Composer's `extra.laminas.config-provider` metadata. These guides do not require FastForward or the proposed major's service provider.

The runtime PSR-11 dependency is the interface package, not another container implementation. The existing typed identifiers support PSR-11 `^1.1 || ^2.0`; 1.0 is incompatible. Published versions predating the [runtime metadata correction](https://github.com/coisa/error-handler/issues/13) need PSR-11 explicitly installed by the application. The [PHP 7.4 alias repair](https://github.com/coisa/error-handler/issues/40) also needs to be present for normal minimum-runtime alias resolution.

## Choose a composition boundary

For the built-in container, supply an optional application PSR-11 parent:

```php
$services = new CoiSA\ErrorHandler\Container\ErrorHandlerContainer($application);

$errors = $services->get(CoiSA\ErrorHandler\ErrorHandler::class);
```

The parent takes priority for IDs it reports as present. Otherwise library aliases and factories resolve services. Instances, including alias results and handlers, are cached. Unknown IDs throw the library's PSR-11 `NotFoundException`; construction errors are wrapped in `ContainerException`. A failed parent service is not a signal to silently substitute a different reporter.

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

The pending minor's `Handler\LogThrowableHandler` is constructed explicitly; it has no library factory, alias or automatic aggregate membership. Providing `LoggerInterface` alone leaves the existing default aggregate unchanged. With an application logger, construct the process handler directly:

```php
$reporter = new CoiSA\ErrorHandler\Handler\LogThrowableHandler($logger);

$process = new CoiSA\ErrorHandler\ErrorHandler($reporter);
```

An application may bind that process object under `ErrorHandler::class` in its parent container. Keep any HTTP renderer/lifecycle composition explicit and separate. Binding any reporter globally under `ThrowableHandlerInterface` also selects the legacy captured-output renderer's reporter. A silent reporter, including private logging or a filtering predicate that returns `false`, leaves empty captured output and causes fallback to diagnostic exception text. Select process reporting and public response construction explicitly; neither silence nor filtering establishes a safe public body. See [the logging contract](api.md#direct-psr-3-logging), [conditional reporting](api.md#conditional-reporting) and [HTTP composition](http.md).

## Factory map and scopes

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

Applications decide when a resolved process handler calls `register()`. For HTTP registration/restoration, rendering and emission boundaries, see [HTTP](http.md).
