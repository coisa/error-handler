# FastForward service provider

`coisa/error-handler` uses FastForward Container's Interop service-provider
contract under the `CoiSA\ErrorHandler` namespace. It requires PHP `^8.3`;
CI targets PHP 8.3–8.5.

## Installation and bootstrap

The library requires `fast-forward/container:^1.10` and the
`container-interop/service-provider:^0.4` interface package at runtime.
Composer installs them for consumers. The provider reuses FastForward's
`AliasFactory::get()`, `InvokableFactory` and `CallableFactory`; it does not ship a second container.
To install the container explicitly in an application:

```sh
composer require fast-forward/container:^1.10
```

FastForward 1.10 requires Config `^1.6`; these lines no longer use PHP-DI or
DotAccessData. Record the actual PHP and locked versions rather than treating a
successful Composer resolution as a complete runtime check. See
[test evidence](testing.md#coverage-and-current-evidence).

The development checkout includes it in `require`. After installing
the checkout dependencies, run the complete [example](https://github.com/coisa/error-handler/blob/master/examples/fast-forward.php):

```sh
php examples/fast-forward.php
```

It reports one warning and restores the prior PHP callbacks. Pass the library
provider class and application provider directly to `container()`. This example
adds a callable reporter for the library's optional reporter discovery. Its
`bootstrap.php` selects the checkout or application autoloader as described in
[example setup](https://github.com/coisa/error-handler/blob/master/examples/README.md):

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$application = new class implements ServiceProviderInterface {
    /**
     * Supplies application service definitions for the integration fixture.
     *
     * @return array<class-string, callable> application service factories
     */
    public function getFactories(): array
    {
        return [
            CallableThrowableHandler::class => static fn(): CallableThrowableHandler => new CallableThrowableHandler(
                /**
                 * Prints only this CLI recipe's synthetic diagnostic.
                 *
                 * @param Throwable $error the converted warning supplied for reporting
                 */
                static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                },
            ),
        ];
    }

    /**
     * Declares no application service extensions.
     *
     * @return array<string, callable> an empty extension map
     */
    public function getExtensions(): array
    {
        return [];
    }
};

$services = container(ErrorHandlerServiceProvider::class, $application);

/**
 * @var ErrorHandlerInterface $errors
 */
$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('FastForward provider warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}
```

For a process-wide handler, keep it registered through termination. For a scoped
operation, use `try/finally` and unregister; the caller then owns any propagated
exception. See [lifecycle](api.md#lifecycle-and-diagnostic-reporting) before combining these patterns.

`getFactories()` declares lazy services; `getExtensions()` returns an empty
array. Neither method, service construction nor `has()` installs PHP callbacks.
There is no provider boot hook. The application decides when to register.

## Definitions and overrides

| Service | Provider behavior |
| --- | --- |
| `ErrorHandlerInterface` / `ErrorHandler` | The same cached process handler; initially unregistered |
| `ThrowableHandlerInterface` | Default aggregate of available callable and PSR-14 reporters |
| `DispatchThrowableHandler` / `DispatchErrorEventThrowableHandler` | Existing PSR-14 adapters; require an application dispatcher |
| `ThrowableStreamFactoryInterface` / `ThrowableResponseFactoryInterface` | Pure generic stream and response adapters; require PSR-17 factories |
| `ErrorHandlerMiddleware` | Cached middleware with a handler separate from the process handler |
| `ServiceProvider\Factory\ErrorHandlerFactory` | Reusable callable that creates a fresh handler on each invocation |
| `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` | Reusable callable that creates fresh middleware and a fresh handler |

The provider has nine explicit definitions. Dispatch handlers and concrete
generic stream/response adapters are resolved through FastForward's constructor
autowiring. Their interface aliases remain explicit, and application definitions
can override either an interface or its concrete implementation when their
maps are merged as shown under [required overrides](#required-overrides). The three
library factories that remain encode optional-handler defaults, reporter
composition and fresh middleware scopes; autowiring alone does not replace those
contracts. The factory-object services use FastForward's `InvokableFactory`.

Bind a required custom renderer through `ThrowableStreamFactoryInterface` or
`ThrowableResponseFactoryInterface`. FastForward can discard a concrete
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

The compatible 2.x LogThrowableHandler and FilteringThrowableHandler remain
explicit application composition. Construct the chosen reporter and bind it under
ThrowableHandlerInterface using the merged-provider pattern below. Neither adapter
is added to the optional discovery list. A logger binding alone does not opt into
direct logging, and a filter does not change the separate public HTTP renderer.

For an explicit PHP-error policy, bind an interface instance or callable under
PhpErrorHandlerInterface. ErrorHandlerFactory passes the configured value into
process and fresh middleware handlers, preserving four arguments, ignored callback
results and the callback's own mask/conversion policy. The same choice is available
under ThrowableHandlerInterface and ShutdownHandlerInterface. Bind callbacks as
values with `ServiceFactory($callback)`; a raw callable in a factory map describes
service construction instead. Required overrides use the aggregate below.

The explicit ErrorHandlerFactory remains responsible for optional defaults and
fresh scopes; this does not require a container to autowire arbitrary union types.
Interface instances take precedence over their callable capability. No callback
is executed or registered during construction. See [adapter contracts](api.md#callable-php-error-policy)
and the [logging](https://github.com/coisa/error-handler/blob/master/examples/logging.php), [filtering](https://github.com/coisa/error-handler/blob/master/examples/filtering.php)
and [PHP-error](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php) examples.

Some autowiring containers advertise `CallableThrowableHandler` through `has()`
even when its required callable cannot be constructed. Optional discovery skips
a PSR-11 not-found result or FastForward's structural `AutowiringException` only
for that callable lookup. This lets an application dispatcher receive both event
routes without a dummy callable. Other callable-construction failures and all
dispatch-adapter failures propagate from the aggregate factory.

FastForward 1.10.0 wraps an executing provider factory's failure in
`ContainerException`, retaining its cause; this is not skipped by the optional
lookup. The final container can nevertheless recover from failed concrete
aggregate construction by autowiring an empty `ThrowableHandlerAggregate` and
caching it. Merging maps does not disable that native fallback. Use an explicit
`ThrowableHandlerInterface` binding for a required reporter and the merged-provider
composition below; verify delivery and failures in the actual application graph.

## Required overrides

The short bootstrap above adds optional reporters and external dependencies.
FastForward 1.10 searches separate providers in the supplied order;
they do not merge their factory maps. In
`container(ErrorHandlerServiceProvider::class, $application)`, a duplicate
`ThrowableHandlerInterface` definition in the application is shadowed by the
library's default. Moving the application first gives it priority, but its PSR-11
construction failure can still fall through to the silent library aggregate.

When replacing an existing service ID, merge the definitions explicitly, with
the application last:

```php
use FastForward\Container\ServiceProvider\AggregateServiceProvider;

$services = container(
    new AggregateServiceProvider(
        new ErrorHandlerServiceProvider(),
        $application
    )
);
```

Here `$application` binds the required reporter or renderer interface.
`AggregateServiceProvider` uses its last definition for each service ID. This
replaces the library default and makes required-reporter failures fail
construction of both process handler and middleware. FastForward normalizes
construction failures into PSR-11 exceptions; its aggregate retains the first
construction failure when no source succeeds. The supported contract is failed
construction after reaching the required factory, without silent fallback to the
library's default reporter. Do not depend on a particular wrapper type or message;
inspect the available cause chain when diagnosing a failed resolution.
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

## Runnable integration recipes

The [usage cookbook](examples.md) exposes complete examples for every built-in adapter:
PSR-14 dispatch and PSR-3 logging, private HTTP reporting, controlled JSON rendering,
custom PHP/shutdown handlers, direct reporter composition and nested scopes.
The cookbook includes each complete program. The [documentation site](https://coisa.github.io/error-handler/#examples)
links the same sources and displays checked excerpts.

## HTTP scopes and fresh instances

Supply `Psr\Http\Message\ResponseFactoryInterface` and
`Psr\Http\Message\StreamFactoryInterface`, for example using an application's
Nyholm PSR-7 factory. The normal middleware service can process sequential
requests and restores an already registered process handler after each one.

The middleware factory deliberately does not resolve the cached `ErrorHandler`
process service. Its `finally` must unregister only its own handler; sharing the
process instance would deactivate the application's bootstrap registration.
`ErrorHandlerFactory` also applies optional PHP/shutdown bindings consistently.
Autowiring selects constructor dependencies, but does not establish a fresh
lifecycle or make a cached instance independent.

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
bounded fatal-error fixtures under PHPUnit Process tests. CI also installs without development dependencies
and resolves a process handler through the provider, proving that FastForward's
factory classes and autowiring are available in production installations.

The full FastForward framework is not required. The Composer package is
`coisa/error-handler`, under the `CoiSA\ErrorHandler` namespace.

Sources: [FastForward Container 1.10.0](https://github.com/php-fast-forward/container/tree/v1.10.0),
[provider contract](https://github.com/container-interop/service-provider/tree/v0.4.1),
[cached provider services](https://github.com/php-fast-forward/container/blob/v1.10.0/src/ServiceProviderContainer.php).

If the built-in handler's reporter fails during a request warning, middleware
propagates that failure and restores callbacks without rendering it through the
reporter again. Ordinary downstream failures still use the configured response
factory. See [failure boundaries](examples.md#failure-and-lifetime-boundaries).

## Reporting and rendering

Middleware requires one scoped instance implementing both `ErrorHandlerInterface`
and `ThrowableHandlerInterface`. It reports downstream failures before invoking
the response factory. The default `ThrowableStreamFactoryInterface` alias now
selects `Http\Message\GenericThrowableStreamFactory`, which uses PSR-17 directly
and produces a controlled body without invoking reporters or output buffering.
A lifecycle-only custom implementation must add reporting to use the middleware.

Reporters keep their void contract and must write HTTP diagnostics privately.
Select the built-in pure JSON/Whoops profile below or implement a custom
stream/response factory; see the complete
[renderer example](examples.md#explicit-json-streams). The retained `ThrowableStreamFactory`
captures a separate public renderer only when explicitly selected. Binding the
old concrete capture class no longer changes the default alias. Bind the
interface for required custom rendering, and do not inject the diagnostic reporter
into a capture adapter after middleware has already reported it.

## JSON profile

`JsonErrorHandlerServiceProvider(bool $debug = false)` declares only two bindings:
the JSON response factory through FastForward's `InvokableFactory`, and the
response interface alias directly to that pure renderer. It does not include the
base provider or reporter. For a required application reporter and PSR-17
implementations, compose the maps explicitly:

```php
$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new JsonErrorHandlerServiceProvider(),
    $application,
));
```

Later aggregate definitions win. A trusted debug choice can be read from
FastForward Config before constructing `new JsonErrorHandlerServiceProvider($debug)`.
Pass a real boolean from a strict caller; the provider does not detect environment
or request state. The [complete recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php) shows public
and debug modes, the application map and a fresh middleware scope. JSON is pure;
the scoped middleware handler supplies the one private report.

Separate providers search first-success sources. JSON before base selects JSON;
base before JSON retains the generic renderer. This is not sufficient to replace
the base's reporter interface: use the aggregate above for required reporting.
Resolve the reusable middleware factory for each independent scope, rather than
expecting repeated `get(ErrorHandlerMiddleware::class)` calls to be fresh.

## Development HTML profile

Install optional `filp/whoops:^2.18.7` in the application selecting this profile.
`WhoopsErrorHandlerServiceProvider` has no constructor and declares only three
bindings: `PrettyPageHandler` through `InvokableFactory`, the pure
`WhoopsThrowableResponseFactory` through FastForward's `CallableFactory`, and its
response interface alias. Its lazy page callback resolves the application's
`PrettyPageHandler` service at render time. Discovery and middleware construction
do not create a page or register native callbacks.

Use one `AggregateServiceProvider` in base, Whoops, application order, as in the
[complete default/configured recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-whoops.php). An optional
package's absence can otherwise fall through between separate providers after a
PSR-11 error. The aggregate selects the intended binding and retains an install
hint through the container's failure chain; it is not a general guarantee that
all concrete factory failures propagate unchanged.

Customize the page by preparing a `PrettyPageHandler` outside container lookup,
applying trusted Config values and binding it with FastForward's `ServiceFactory`.
The container caches that service; sequential renders reuse its configuration and
replace diagnostic data. Every render creates a fresh, unregistered Whoops run,
response and stream. Distinct middleware scopes still use distinct core handlers.
There is no concurrent rendering isolation or comprehensive redaction promise.

FastForward may recover a failing concrete page factory by autowiring a default
page even inside an aggregate. A binding returning the wrong page type and a page
whose `handle()` throws are different runtime failures, not successful fallbacks.
The provider follows the container's resolution contract; it does not install a
second resolver, clone pages or suppress PHP warnings. Configure required page
state before lookup when those configuration errors must be observed immediately.

The manual renderer's callable parameter remains available for explicit application
composition. Both profiles select pure renderers, never
`ReportingThrowableResponseFactory`: middleware has already reported.
See [HTTP boundaries](http.md) for trusted debug policy and diagnostic exposure.

## Troubleshooting composition

| Symptom | Check |
| --- | --- |
| A required reporter is silent | Bind `ThrowableHandlerInterface` and merge base, profile if selected, then application maps in one aggregate. A separate application provider can be shadowed or skipped after a construction failure. |
| A request deactivates the process handler | Give middleware a distinct handler. Resolve and invoke its factory for each independent scope; do not pre-register that handler. |
| A page override appears to be ignored | Configure the page before lookup and bind the instance with `ServiceFactory`. A failed concrete factory can fall back to an autowired default page. |
| Whoops classes are unavailable | Install optional `filp/whoops:^2.18.7` in the consuming application and confirm that the selected autoloader includes it. |

For reporter echo, duplicate reports and diagnostic exposure, follow
[HTTP boundaries](http.md). Container failure normalization does
not make an invalid required binding optional.
