# FastForward service provider

This integration belongs to the unreleased next major of `coisa/error-handler`.
It follows the provider contract used by FastForward Container 1.6.0 and
Framework 1.4.0. The existing `CoiSA\ErrorHandler` namespace remains the current
API; a possible future move to php-fast-forward has not happened.

## Installation and bootstrap

The library requires the small `container-interop/service-provider:^0.4.1`
interface package. Its core and provider do not require FastForward or a full
framework. Applications choosing FastForward install its container explicitly:

```sh
composer require fast-forward/container:^1.6
```

The development checkout already includes it in `require-dev`. After installing
the checkout dependencies, run the complete [example](../examples/fast-forward.php):

```sh
php examples/fast-forward.php
```

It reports one warning and restores the prior PHP callbacks. Merge the library
and application definitions into one provider, with the application last:

```php
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use function FastForward\Container\container;

// $application is the ServiceProviderInterface implementation in the example.
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
$errors = $services->get(ErrorHandlerInterface::class);
$errors->register();
```

For a process-wide handler, keep it registered through termination. For a scoped
operation, use `try/finally` and unregister; the caller then owns any propagated
exception. See [lifecycle](api.md#lifecycle) before combining these patterns.

`getFactories()` declares lazy services; `getExtensions()` returns an empty
array. Neither method, service construction nor `has()` installs PHP callbacks.
There is no provider boot hook. The application decides when to register.

## Definitions and overrides

| Service | Provider behavior |
| --- | --- |
| `ErrorHandlerInterface` / `ErrorHandler` | The same cached process handler; initially unregistered |
| `ThrowableHandlerInterface` | Default aggregate of available callable and PSR-14 reporters |
| `DispatchThrowableHandler` / `DispatchErrorEventThrowableHandler` | Existing PSR-14 adapters; require an application dispatcher |
| `ThrowableStreamFactoryInterface` / `ThrowableResponseFactoryInterface` | Existing rendering adapters; require PSR-17 stream/response factories |
| `ErrorHandlerMiddleware` | Cached middleware with a handler separate from the process handler |
| `Container\Factory\ErrorHandlerFactory` | Reusable callable that creates a fresh handler on each invocation |
| `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` | Reusable callable that creates fresh middleware and a fresh handler |

Names in the table are relative to `CoiSA\ErrorHandler`; reporter classes are
under `Handler`, HTTP adapters under `Http\Message`, and middleware under
`Http\Middleware`.

Bind `ThrowableHandlerInterface` in the application provider for an explicit
reporter. Alternatively provide `CallableThrowableHandler` or
`Psr\EventDispatcher\EventDispatcherInterface` to populate the aggregate. The
PSR-14 path dispatches both the original throwable and an `ErrorEvent` wrapper;
subscribe intentionally to avoid duplicate application reporting. An aggregate
with no configured reporters is silent. No logger is silently installed.

Some autowiring containers advertise `CallableThrowableHandler` through `has()`
even when its required callable cannot be constructed. Optional discovery skips
only a PSR-11 not-found result from `get()`; other construction exceptions are
propagated. FastForward 1.6.0 can itself translate a failed concrete dependency
into not-found, so the library cannot distinguish that from an absent optional
reporter. Use an explicit `ThrowableHandlerInterface` binding for a required
reporter and the merged-provider composition above; the next paragraph explains
why both are needed.

`AggregateServiceProvider` uses the last definition for each service ID, so the
application's required reporter replaces the default. Passing separate providers
to `container(...)` instead creates a fallback chain: FastForward may suppress
the application's PSR container failure and use the library's silent aggregate.
The merged form prevents that fallback and makes required-reporter failures
fail construction of both process handler and middleware. FastForward still
translates PSR failures to not-found; it does not preserve their original cause.
An ordinary application exception continues to propagate.

Optional `PhpErrorHandlerInterface` and `ShutdownHandlerInterface` bindings are
passed to fresh handlers too. The provider reuses the package's PSR-11 factories
and introduces no separate configuration format or automatic event subscriptions.
The Laminas `ConfigProvider` and `ErrorHandlerContainer` remain available.

## HTTP scopes and fresh instances

Supply `Psr\Http\Message\ResponseFactoryInterface` and
`Psr\Http\Message\StreamFactoryInterface`, for example using an application's
Nyholm PSR-7 factory. The normal middleware service can process sequential
requests and restores an already registered process handler after each one.

FastForward caches `get()` results. For nested scopes, resolve a factory object
and invoke it for each middleware instance:

```php
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;

$makeMiddleware = $services->get(ErrorHandlerMiddlewareFactory::class);
$outer = $makeMiddleware($services);
$inner = $makeMiddleware($services);
```

Likewise invoke the `Container\Factory\ErrorHandlerFactory` service to create
fresh direct handlers. Avoid sharing the process handler with middleware and
do not pre-register the middleware's own handler. The reusable factory pattern
does not provide concurrent-request isolation: PHP callback stacks remain
process-wide. Reuse instances for sequential work when practical because PHP
retains registered shutdown callbacks until process exit.

## Verification and migration readiness

The integration tests use the real released FastForward container, including
provider-class bootstrap, explicit and optional reporters, custom PHP/shutdown
handlers, event dispatch, fresh factories, nested HTTP scopes and restoration
after renderer failure. The full [test command](testing.md) retains the existing
fatal-error and HTTP checks. CI also loads the provider without development
dependencies, proving that installing the whole FastForward framework is not
necessary to use the core library.

This separates the public Interop/PSR contracts from the optional framework
consumer. A later move still needs an explicit package/namespace decision,
release and migration guidance; this provider is not a claim that those changes
have already been made.

Sources: [FastForward Container 1.6.0](https://github.com/php-fast-forward/container/tree/v1.6.0),
[provider contract](https://github.com/container-interop/service-provider/tree/v0.4.1),
[factory lifetime pattern](https://github.com/php-fast-forward/container/blob/v1.6.0/docs/advanced/special-patterns.rst).
