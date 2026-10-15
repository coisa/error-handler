---
name: coisa-error-handler
description: Integrate CoiSA Error Handler (coisa/error-handler) into PHP applications using explicit process handlers, FastForward service providers or PSR-15 middleware, and verify reporting and callback restoration.
license: MIT
---

# CoiSA Error Handler integration

Package: `coisa/error-handler`. Source and maintenance:
[coisa/error-handler on GitHub](https://github.com/coisa/error-handler).
This skill belongs to that library and retains its package identity when copied
into a consuming project.

Use the installed package or selected checkout as the API source. This skill
describes the PHP 8.3+ development line after 2.0; verify the version before
using its provider or changed exit-status contract. A future php-fast-forward
move is not an existing package name or namespace alias.

## Choose the scope

For a CLI or process bootstrap, create one handler with an application-owned
reporter and keep it registered until process exit when it must see uncaught
exceptions. For a callable operation whose caller catches failures, unregister
in `finally`. PHP runs that `finally` before dispatching an uncaught exception.

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

$errors = new ErrorHandler(new CallableThrowableHandler(
    static function (Throwable $error): void {
        error_log($error->getMessage()); // Replace with an appropriate private sink.
    }
));
$errors->register();
```

Load Composer's autoloader first. Direct `handleThrowable()` calls return;
the registered uncaught-exception callback exits 1 after successful reporting.
Recoverable warnings are reported and execution continues. The default error
conversion honors suppression and `error_reporting()`.

## Wire FastForward definitions

Use `CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider` with
`FastForward\Container\container()`. It implements the Interop provider contract
(`getFactories()` and `getExtensions()`), and resolves
`CoiSA\ErrorHandler\ErrorHandlerInterface`. To override the reporter binding
`CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface`, merge definitions with
`FastForward\Container\ServiceProvider\AggregateServiceProvider`: library first,
application **last**, then pass that one provider into `container(...)`.

FastForward Container is a runtime dependency in this development line.
`CoiSA\ErrorHandler\Container\ErrorHandlerContainer` has been removed. Concrete
dispatch and HTTP adapters use constructor autowiring; the provider retains
explicit factories for optional callbacks, reporter composition and fresh scopes.
The Interop provider is the single container integration entrypoint;
`ConfigProvider` and its Laminas discovery metadata have been removed. Provider
factories live under `ServiceProvider\Factory`. Consult the migration guide for
removed and moved class names.

Separate variadic providers allow FastForward to fall back to the library's
silent default if the application's reporter fails with a PSR container error.
Merged definitions replace that default and make required-reporter construction
fail. FastForward may translate the PSR error to not-found; do not promise that
its original exception cause survives. Use the executable example in the
matching checkout for the complete composition.

Container versions may normalize ordinary application failures too, and may
attempt construction more than once through delegation/autowiring. Require failed
construction, not an exact exception type or a single factory invocation. This
does not change the handler's no-retry contract after a reporter itself fails.

The factory maps do not install PHP callbacks: explicitly register the resolved
process handler at bootstrap. An empty default reporter aggregate is silent;
configure a reporter or an event dispatcher before expecting diagnostics.

FastForward caches resolved instances, including middleware. To create a fresh
handler, resolve `CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory` and
invoke it with the container. For fresh middleware, resolve and invoke
`CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory`.
Do not mistake repeated `get()` calls for a transient lifetime.

Bind required custom renderers through `ThrowableStreamFactoryInterface` or
`ThrowableResponseFactoryInterface`. FastForward may discard a concrete class
definition's PSR construction failure and rebuild it through autowiring; explicit
interface bindings prevent that fallback. Keep application definitions last.

## Wire HTTP deliberately

Supply PSR-17 response and stream factories. The provider creates middleware
with a handler distinct from the process service; it restores native callbacks
after processing and after renderer failure. Create separate middleware for
nested scopes. This library does not make callbacks local to concurrent fibers
or requests, and it does not emit HTTP responses.

Reporters and renderers have different output responsibilities. Empty captured
reporter output becomes `Internal Server Error`; emitted text becomes the public
body. Keep private diagnostics out of renderer output. Renderers must use
removable, balanced buffers and must not close caller-owned buffers.

## Verify the integration

Exercise a reported warning, an uncaught throwable in a bounded child process,
and callback restoration after success and failure. If using middleware, also
exercise nesting beneath a registered process handler and renderer failure.
Check delivery counts, response redaction and exit status, not just that a
service can be resolved. Never run a fatal-error experiment in a user process.

In the library checkout, use `composer --no-plugins check` and
`php examples/fast-forward.php`. In a consuming application, add focused tests
against its configured reporters and middleware; the library's passing suite
does not prove remote logger delivery or application-specific transport behavior.

For full examples, consult the matching checkout's `docs/fast-forward.md`,
`docs/api.md` and `docs/testing.md`, or the
[source repository](https://github.com/coisa/error-handler). Keep the observed
version, integration changes, executed checks and remaining limits explicit.
