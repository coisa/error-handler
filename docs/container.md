# Existing container integration in 2.x

2.x retains `Container\ErrorHandlerContainer`, `Container\ConfigProvider`, factories under `Container\Factory` and Composer's `extra.laminas.config-provider` metadata. These guides do not require FastForward or the proposed major's service provider.

The runtime PSR-11 dependency is the interface package, not another container implementation. The existing typed identifiers support PSR-11 `^1.1 || ^2.0`; 1.0 is incompatible. Published versions predating the [runtime metadata correction](https://github.com/coisa/error-handler/issues/13) need PSR-11 explicitly installed by the application. The [PHP 7.4 alias repair](https://github.com/coisa/error-handler/issues/40) also needs to be present for normal minimum-runtime alias resolution.

## Decide how to get a handler

A service ID is the string passed to a container's `get()` or `has()` method, usually an interface or class name. An alias redirects one ID to another. A factory creates a value from services in that container. These are [PSR-11 container](https://www.php-fig.org/psr/psr-11/) integration concepts; they do not install native PHP callbacks.

| Existing application setup | Choose | Configuration owner |
| --- | --- | --- |
| No dependency injection container | Construct `new ErrorHandler($reporter)` directly | The application supplies its reporter and optional PHP-error/shutdown adapters. |
| An application PSR-11 container | Wrap it with `new ErrorHandlerContainer($application)` | The application parent supplies external implementations and service overrides. |
| A container consuming Laminas-style configuration | Merge `(new ConfigProvider())->getDependencies()` into its definitions | The application's container interprets aliases/factories and controls sharing. |

The snippets below illustrate these boundaries. For a complete program with imports, application services, all factory resolutions and identity checks, use [the container recipe](examples.md#existing-container-and-configprovider-integration). Follow its [dependency setup](../examples/README.md#application-dependencies) before running it.

## Choose a composition boundary

For the built-in container, supply an optional application PSR-11 parent:

```php
$services = new CoiSA\ErrorHandler\Container\ErrorHandlerContainer($application);

$errors = $services->get(CoiSA\ErrorHandler\ErrorHandler::class);
```

The parent takes priority for IDs it reports as present. Otherwise library aliases and factories resolve services. The built-in container caches values it resolves locally, including alias results and handlers. Parent lookups delegate directly; the parent owns their lifetime. Unknown IDs throw the library's PSR-11 `NotFoundException`; construction errors are wrapped in `ContainerException`. A failed parent service is not a signal to silently substitute a different reporter.

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

These are the three aliases returned by `ConfigProvider::getAliases()`. Names are relative to `CoiSA\ErrorHandler`:

| Requested ID | Default target ID | Consequence |
| --- | --- | --- |
| `Handler\ThrowableHandlerInterface` | `Handler\ThrowableHandlerAggregate` | The core and captured-output renderer share the selected reporter service. |
| `Http\Message\ThrowableStreamFactoryInterface` | `Http\Message\ThrowableStreamFactory` | The built-in response factory selects the captured-output adapter. |
| `Http\Message\ThrowableResponseFactoryInterface` | `Http\Message\ThrowableResponseFactory` | The middleware selects the built-in response/capture path unless overridden. |

They are container service aliases, not PHP `class_alias()` declarations. `AliasFactory($targetId)` calls `get($targetId)` and returns its exact value. It does not construct a new object or establish a global singleton. An application parent can override either a requested interface ID or its target ID. Configure the graph before resolution: changing only a target later does not refresh an already cached local alias. A parent entry for the requested ID still takes priority on each lookup.

`ErrorHandlerFactory` requires `ThrowableHandlerInterface` and checks the container for optional `PhpErrorHandlerInterface` and `ShutdownHandlerInterface` services. Missing optional services are passed as null, selecting the core defaults. These two optional IDs have no default entries in `ConfigProvider`; provide application instances explicitly when replacing their policy.

The built-in container reads its fixed maps at construction and has no public setter for replacing factories or aliases. Supply overrides through its application parent. In a ConfigProvider-consuming container, modify the returned dependency maps using that container's supported configuration; its resolution and sharing rules apply. Adding a factory definition is distinct from attaching its reporter to an aggregate.

Repeated `get(ErrorHandler::class)` returns the cached handler. Invoking a new `ErrorHandlerFactory` with the container constructs another core handler, while its dependencies can remain shared.

Invoking `ErrorHandlerMiddlewareFactory` constructs a middleware wrapper but asks the container for its handler. That handler may still be cached. For independent nested scopes, explicitly construct a distinct ErrorHandler and middleware; do not treat a fresh wrapper as proof of a fresh handler. The existing middleware factory does not isolate process and request handlers automatically.

Applications decide when a resolved process handler calls `register()`. For HTTP registration/restoration, rendering and emission boundaries, see [HTTP](http.md).

## Troubleshoot service resolution

| Observation | Check next |
| --- | --- |
| `has($id)` is true but `get($id)` throws | `has()` recognizes a parent ID, alias or factory; it does not construct or validate its dependencies. Inspect the private exception cause chain for the missing or failing collaborator. |
| The default core receives failures but nothing is reported | Its aggregate starts empty. Supply `CallableThrowableHandler`, the intended event route or an explicit `ThrowableHandlerInterface` override. A logger or arbitrary reporter-class registration alone is insufficient. |
| Middleware construction fails for an HTTP factory ID | Supply application implementations under both PSR-17 `ResponseFactoryInterface` and `StreamFactoryInterface`; installing interface packages alone does not provide them. |
| A custom PHP-error or shutdown adapter is ignored | Bind the interface ID requested by `ErrorHandlerFactory`, or construct the core explicitly with that adapter. Providing only its concrete class ID does not replace an optional interface lookup. |
| Two middleware wrappers use the same core | Their factory resolves `ErrorHandler::class` from the container. Explicitly construct distinct handlers for independent scopes; a new wrapper does not establish a new lifecycle. |
| A service change is absent from an existing handler | Existing objects retain their dependencies and locally resolved services are cached. Configure the intended graph before resolution and construct a new scope when needed. |

Use [the bounded examples](../examples/verify.md) to verify the selected composition. Keep private diagnostics out of public HTTP output; a successful container lookup does not establish that response boundary.
