# Container integration in 2.x

2.x retains `Container\ErrorHandlerContainer`, `Container\ConfigProvider`, factories under `Container\Factory` and Composer's `extra.laminas.config-provider` metadata. These legacy routes do not require FastForward. The [optional Interop providers](#optional-interop-and-fastforward-providers) below are additive and require an application using PHP 8.3+; they do not adopt the proposed major's middleware or defaults.

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

`Http\Message\JsonThrowableResponseFactory` and `ReportingThrowableResponseFactory` are explicit opt-in services. The legacy routes do not select them. Construct them with application-owned PSR-17 factories and a private, non-output reporter, then construct a distinct middleware scope as shown in the [JSON composition](http.md#opt-in-json-responses-and-explicit-reporting), or choose the JSON Interop profile below. An application may supply the composed response factory under `ThrowableResponseFactoryInterface`, but that override does not make the legacy cached lifecycle handler a fresh request scope. Keep process and public rendering composition intentional.

## Optional Interop and FastForward providers

The optional `ServiceProvider\ErrorHandlerServiceProvider`, `JsonErrorHandlerServiceProvider` and `WhoopsErrorHandlerServiceProvider` profiles were published in 2.5.0. Select `coisa/error-handler:^2.5` and verify the installed source for the chosen profile. They use `Interop\Container\ServiceProviderInterface` and FastForward's reusable factories. Core PHP 7.4 consumers retain the legacy routes. The examples and bundled skill keep their common `^2.4` baseline. The provider integration needs PHP 8.3+ and FastForward installed as an **application runtime dependency**:

```sh
composer require 'coisa/error-handler:^2.5' 'fast-forward/container:^1.9' 'nyholm/psr7:^1.8' --no-plugins --no-scripts
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

The base `ErrorHandlerServiceProvider` has no arguments. It defines lifecycle aliases, reusable scope factories and the existing capture defaults, requiring the application's reporter instead of selecting an empty aggregate. It registers neither JSON, Whoops nor an implicit reporter. Merely providing PSR-17 or a logger does not select a profile or compose reporting.

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

### Development HTML profile

Select `WhoopsErrorHandlerServiceProvider` only after the application's trusted development and HTML-format decision. Install optional `filp/whoops:^2.18.7` where that renderer is used. With the same application PSR-17 and private reporter definitions, the default form is:

```php
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;

$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new WhoopsErrorHandlerServiceProvider(),
    $application,
));
```

The Whoops profile has no constructor arguments. It declares only `PrettyPageHandler`, its pure `WhoopsThrowableResponseFactory`, the reporting decorator and the selected response alias. It does not instantiate or merge the base provider. The application composes the independent maps in one aggregate, ordered base, Whoops, application; the last definition wins. The profile does not select JSON or install callbacks. Provider discovery, renderer resolution and scope construction create no default page; rendering resolves the page service lazily. Choosing this diagnostic profile is the explicit opt-in, so it has no additional debug boolean or request/Accept detection.

For page configuration, override `PrettyPageHandler::class` in the application's factory map. The default uses FastForward's `InvokableFactory`. This example reads application Config and prepares the page before composition, then exposes it through `ServiceFactory`. Configuration failures therefore occur outside container lookup:

```php
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Whoops\Handler\PrettyPageHandler;

use function FastForward\Config\config;

$settings = config(['development' => ['page_title' => 'Application diagnostics']]);
$page = new PrettyPageHandler();

$page->setPageTitle($settings->get('development.page_title'));
$factories[PrettyPageHandler::class] = new ServiceFactory($page);

$application = new ArrayServiceProvider($factories);

$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new WhoopsErrorHandlerServiceProvider(),
    $application,
));
```

FastForward caches the page service. Sequential renders reuse configuration while Whoops replaces its exception, inspector and generated template data; each render creates a fresh Run, PSR response and stream. The shared page retains its latest diagnostic until the next render. This does not promise concurrent reuse or behavior of arbitrary subclasses. A private reporter runs once before page lookup; reporter failure prevents rendering, and failures escaping the page callback or renderer propagate without another report. The concrete renderer remains pure when resolved directly. Whoops stays optional: loading and constructing the profile without it is inert; selecting the renderer checks the dependency before page lookup and supplies installation guidance if it is absent.

Container resolution remains FastForward's responsibility. The aggregate fixes definition precedence; it does not disable autowiring fallback after a concrete page factory throws. With FastForward 1.9.1, a failing `PrettyPageHandler` factory can be replaced by an autowired default page, losing that intended configuration. A returned incompatible service produces `TypeError`; a page's `handle()` failure escapes unchanged. Preparing the configured page before composition avoids putting its configuration failure inside lookup; it does not change the semantics of factories that throw during `get()`.

Separate providers have a different boundary: their first successful resolution wins. With Whoops installed, Whoops before base selects HTML; base first selects legacy capture. Without Whoops, the separate Whoops/base/application route can fall back to a reporting decorator around legacy capture and report the same original twice. Use the aggregate above for this profile: in the real no-Whoops consumer it retains the missing-dependency cause and hint instead of returning that fallback. These observed container paths are not adapter retries.

The [complete provider recipe](../examples/provider-whoops.php) exercises default and configured services with two fresh middleware scopes and one cached configured page. Default pages expose diagnostic data; hiding selected keys does not redact messages, traces, source excerpts or every secret. Read the [HTML disclosure and failure contract](http.md#opt-in-development-html-with-whoops) before enabling this profile. The following scope rules apply to either renderer profile. Aggregate definitions use the opposite precedence from separate providers.

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

## Optional development HTML composition

For manual or legacy-container composition, supply application PSR-17 factories and a callable supplying `Whoops\Handler\PrettyPageHandler`, then compose private reporting with `ReportingThrowableResponseFactory` in 2.x. The renderer adds no legacy aliases or ConfigProvider bindings; the separate opt-in [Whoops Interop profile](#development-html-profile) supplies its own map. The renderer can be cached with configuration; do not capture a current request. A manual callback may create a fresh page or reuse one for sequential rendering under the state limits above. Construct it only after the application's trusted development/format decision. Core/container installation does not require Whoops; choosing the adapter without installing `filp/whoops:^2.18.7` raises `LogicException`. See [the HTML guide](http.md#opt-in-development-html-with-whoops) and [manual recipe](../examples/http-whoops.php). Existing container/discovery/middleware defaults remain unchanged.

## Troubleshoot service resolution

This table applies to `Container\ErrorHandlerContainer` and its `Container\Factory` integrations. The optional Interop profiles above have their own scope and fallback rules.

| Observation | Check next |
| --- | --- |
| `has($id)` is true but `get($id)` throws | `has()` recognizes a parent ID, alias or factory; it does not construct or validate its dependencies. Inspect the private exception cause chain for the missing or failing collaborator. |
| The default core receives failures but nothing is reported | Its aggregate starts empty. Supply `CallableThrowableHandler`, the intended event route or an explicit `ThrowableHandlerInterface` override. A logger or arbitrary reporter-class registration alone is insufficient. |
| Middleware construction fails for an HTTP factory ID | Supply application implementations under both PSR-17 `ResponseFactoryInterface` and `StreamFactoryInterface`; installing interface packages alone does not provide them. |
| A custom PHP-error or shutdown adapter is ignored | Bind the interface ID requested by `ErrorHandlerFactory`, or construct the core explicitly with that adapter. Providing only its concrete class ID does not replace an optional interface lookup. |
| Two middleware wrappers use the same core | Their factory resolves `ErrorHandler::class` from the container. Explicitly construct distinct handlers for independent scopes; a new wrapper does not establish a new lifecycle. |
| A service change is absent from an existing handler | Existing objects retain their dependencies and locally resolved services are cached. Configure the intended graph before resolution and construct a new scope when needed. |

Use [the bounded examples](../examples/verify.md) to verify the selected composition. Keep private diagnostics out of public HTTP output; a successful container lookup does not establish that response boundary.
