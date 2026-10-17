# Migrating from 2.x

This is the migration contract for the unreleased next major. It does not describe an already published Packagist release.

Use the [Interop service provider](fast-forward.md) for container integration
without changing the existing package or namespace. The short bootstrap is
`container(ErrorHandlerServiceProvider::class, $application)` for external
dependencies and optional reporters. To override existing library service IDs,
merge definitions with `AggregateServiceProvider`, library first and application
last, so a required reporter is not shadowed or replaced after construction
fails. Registration remains explicit;
use factory services for fresh/nested scopes because container lookups are cached.
The Interop provider is the single container integration entrypoint.
The Laminas `ConfigProvider` adapter and standalone `ErrorHandlerContainer`
have been removed in favor of FastForward Container with this provider.

1. Upgrade the application to PHP `^8.3`; CI targets PHP 8.3–8.5. Resolve Composer dependencies again. The library does not commit `composer.lock`; resolve development dependencies for your PHP version with `composer update --no-plugins --no-scripts`. Applications keep their own locks.
2. Expect exit status **1** after a globally uncaught `Throwable` is reported successfully. Version 2 could exit with status 0. A reporter that explicitly terminates the process still controls its own exit code. Direct `handleThrowable()` calls do not exit.
3. Pair `register()` and `unregister()` in reverse order. A repeated `register()` on the same instance is idempotent, not reference counted. Out-of-order unregistration now throws `LogicException` instead of silently removing someone else's callback. Use separate instances for nested middleware and do not pre-register the middleware's own instance.
4. A reporter must not call its own `ErrorHandler` recursively. After a reporter throws, that instance stops retrying it until it is unregistered and registered again. The failure propagates to the caller or PHP; it is not silently swallowed. Only the active registration receives shutdown notifications, preventing duplicates across nested handlers.
5. Review custom HTTP rendering. The provider defaults to a pure generic renderer, independent of reporter output. In the explicit output-capture adapter, empty output now produces `Internal Server Error` instead of a stringified exception and stack trace; the string `0` is preserved. Exception messages no longer become HTTP reason phrases. Codes 400–599 map to their matching status; other integer codes and string SQLSTATE codes map to 500.
6. Compatible 2.x maintenance already removed Docker/Compose and consolidated bounded process fixtures under PHPUnit. Keep those Composer/PHPUnit checks; CI supplies the PHP 8.3–8.5 development matrix. No application runtime image is supplied.

7. Replace `new Container\ErrorHandlerContainer($parent)` with
   `FastForward\Container\container(ErrorHandlerServiceProvider::class, $parent)`
   when the parent only supplies external PSR dependencies. For application
   overrides, use the merged provider composition above. FastForward is now a
   runtime dependency, including its transitive packages; consumers resolve them
   through Composer. Do not rely on the removed package-specific
   `Container\Exception\ContainerException` or `NotFoundException`; catch the
   corresponding PSR-11 exception interfaces instead.
8. Replace removed constructor-only factories as follows. Names below are relative
   to `CoiSA\ErrorHandler` except the FastForward factory classes.

| Removed API | Replacement |
| --- | --- |
| `Container\Factory\AliasFactory` | `FastForward\Container\Factory\AliasFactory::get($serviceId)` |
| `Container\Factory\DispatchThrowableHandlerFactory` | Autowire `Handler\DispatchThrowableHandler` |
| `Container\Factory\DispatchErrorEventThrowableHandlerFactory` | Autowire `Handler\DispatchErrorEventThrowableHandler` |
| `Container\Factory\ThrowableResponseFactoryFactory` | Autowire `Http\Message\ThrowableResponseFactory` |
| `Container\Factory\ThrowableStreamFactoryFactory` | Autowire `Http\Message\ThrowableStreamFactory` |
| `Container\Factory\ErrorHandlerMiddlewareFactory` | `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory`, which creates a fresh handler |
| `Container\ConfigProvider` | `ServiceProvider\ErrorHandlerServiceProvider`; remove Laminas `config-provider` discovery and register the Interop provider with FastForward |
| `Container\Factory\ErrorHandlerFactory` | `ServiceProvider\Factory\ErrorHandlerFactory` |
| `Container\Factory\ThrowableHandlerAggregateFactory` | `ServiceProvider\Factory\ThrowableHandlerAggregateFactory` |

Update factory imports and service identifiers to the new namespaces; no aliases
for the old names are provided. All three remaining factories live under
`ServiceProvider\Factory` because they encode lifecycle and reporter-composition
behavior. Use the Interop provider with constructor autowiring; a framework-native
configuration adapter is no longer shipped. `laminas/laminas-servicemanager` is
no longer a development dependency; unrelated transitive Laminas packages may
still be required by FastForward.

Public handler interfaces retain their names and method signatures. The PHP
floor, required FastForward dependency, removed container/factories, CLI exit
semantics, lifecycle rejection and HTTP output defaults warrant a major release.

The compatible 2.x adapters LogThrowableHandler, FilteringThrowableHandler and
CallablePhpErrorHandler remain available with their existing contracts. Logging
keeps the exact message and reserved exception context; filtering skips only an
explicit delegate; PHP-error callbacks retain four arguments, ignored returns and
application-owned mask policy. They are not automatically discovered or attached
to the default aggregate. See [their API contracts](api.md#direct-psr-3-logging).
The constructor remains interface-typed in this candidate; accepting bare
callables directly is [tracked separately in #70](https://github.com/coisa/error-handler/issues/70).

Before upgrading a real application, run the [documented checks](testing.md) and application-level tests for the actual reporter, middleware stack and PHP SAPI. These isolated fixtures do not establish reliability of third-party loggers, remote services or production infrastructure.

For the built-in handler, failed-reporter suppression applies only during a
registered lifecycle. Independent direct calls remain usable after a reporter
failure. Middleware propagates a failed reporter instead of retrying it during
response rendering. Custom lifecycle implementations retain their own policy.
These Throwable boundaries do not recover an application callback's exit/die or
native fatal termination.
See the [complete usage recipes](examples.md) and [release workflow](releases.md).

## Separate HTTP reporting from rendering

The middleware constructor now requires `ErrorHandlerInterface&ThrowableHandlerInterface`.
The built-in ErrorHandler already satisfies this. Custom lifecycle-only implementations
must also implement diagnostic delivery. Middleware reports a downstream Throwable
once, then invokes the response factory; reporter failures propagate without rendering.

The provider's stream alias now selects GenericThrowableStreamFactory. Reporter
output is not implicitly captured as the response body. HTTP reporters must use
private sinks. Implement ThrowableStreamFactoryInterface to return JSON or another
public stream directly; the cookbook includes a complete JSON example without echo.
ThrowableHandlerInterface remains void, preserving CLI, event and aggregate roles.

ThrowableStreamFactory retains explicit output capture for public templates.
Configure a separate public renderer there, rather than a reporter middleware
already invoked. Its old concrete service ID no longer controls the default
stream alias.

The JSON and optional Whoops response adapters published in 2.5.0 remain available.
Pass their pure renderers directly to middleware. Remove the explicit
`ReportingThrowableResponseFactory` wrapper used by 2.x middleware, which did not
report independently. The wrapper remains available for other explicit pipelines.
`JsonErrorHandlerServiceProvider` and `WhoopsErrorHandlerServiceProvider` now alias
`ThrowableResponseFactoryInterface` to their pure concrete renderers. Each profile
returns only its own map; compose base, selected profile and application in one
`AggregateServiceProvider` for required reporters/overrides. JSON retains explicit
`bool $debug = false`; the Whoops provider has no constructor and uses the
application-overridable `PrettyPageHandler` service. A configured page is reused
sequentially; middleware scopes, Whoops runs and response bodies remain distinct.
