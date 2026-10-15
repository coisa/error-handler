# FastForward service provider

This integration belongs to the unreleased next major of `coisa/error-handler`.
It follows the provider contract used by FastForward Container 1.6.0 and
Framework 1.4.0. The existing `CoiSA\ErrorHandler` namespace remains the current
API; a possible future move to php-fast-forward has not happened.

## Installation and bootstrap

The library requires `fast-forward/container:^1.6` and the
`container-interop/service-provider:^0.4.1` interface package at runtime.
Composer installs them for consumers. The provider reuses FastForward's
`AliasFactory::get()` and `InvokableFactory`; it does not ship a second container.
To install the container explicitly in an application:

```sh
composer require fast-forward/container:^1.6
```

The development checkout includes it in `require`. After installing
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
| `ServiceProvider\Factory\ErrorHandlerFactory` | Reusable callable that creates a fresh handler on each invocation |
| `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` | Reusable callable that creates fresh middleware and a fresh handler |

The provider has nine explicit definitions. Dispatch handlers and concrete
stream/response adapters are resolved through FastForward's constructor
autowiring. Their interface aliases remain explicit, and application definitions
can override either an interface or its concrete implementation. The three
library factories that remain encode optional-handler defaults, reporter
composition and fresh middleware scopes; autowiring alone does not replace those
contracts. The factory-object services use FastForward's `InvokableFactory`.

Bind a required custom renderer through `ThrowableStreamFactoryInterface` or
`ThrowableResponseFactoryInterface`. FastForward 1.6.0 can discard a concrete
class definition's PSR-11 construction failure and fall back to autowiring that
class. The same failure on an explicitly bound interface prevents construction,
because the interface has no autowire fallback. Successful concrete overrides
work, but they do not provide the same failure contract. The integration suite
checks both successful concrete overrides and required-interface failures.

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
Container 1.6.0 propagates ordinary application exceptions directly; newer
versions such as 1.8.0 normalize those too. The supported contract is failed
construction after reaching the required factory, without silent fallback;
it does not guarantee exception type, message or cause preservation.
Container resolution may attempt a failing construction more than once while
trying its delegates and autowiring. Application factories must tolerate those
attempts; this differs from the handler's no-retry contract after reporting fails.

Optional `PhpErrorHandlerInterface` and `ShutdownHandlerInterface` bindings are
passed to fresh handlers too. The provider reuses the package's PSR-11 factories
and introduces no separate configuration format or automatic event subscriptions.
The Interop service provider is the only supported container integration.
`ConfigProvider`, its Laminas discovery metadata, `ErrorHandlerContainer` and the
constructor-only factories have been removed. Provider factories all live under
`ServiceProvider\Factory`; see [migration](migration.md). Provider consumers must
support constructor autowiring, as FastForward's `container()` does.

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

Likewise invoke the `ServiceProvider\Factory\ErrorHandlerFactory` service to create
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
fatal-error and HTTP checks. CI also installs without development dependencies
and resolves a process handler through the provider, proving that FastForward's
factory classes and autowiring are available in production installations.

The full FastForward framework is not required. A later package move still needs
an explicit package/namespace decision,
release and migration guidance; this provider is not a claim that those changes
have already been made.

Sources: [FastForward Container 1.6.0](https://github.com/php-fast-forward/container/tree/v1.6.0),
[provider contract](https://github.com/container-interop/service-provider/tree/v0.4.1),
[factory lifetime pattern](https://github.com/php-fast-forward/container/blob/v1.6.0/docs/advanced/special-patterns.rst).
