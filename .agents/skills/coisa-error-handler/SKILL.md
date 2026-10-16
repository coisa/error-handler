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
describes the unreleased PHP `^8.3` major after 2.x, tested on PHP 8.3–8.5; verify the version before
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

## Select explicit adapters

The compatible 2.x adapters remain opt-in. Handler\LogThrowableHandler uses the
application's PSR-3 logger, ERROR by default or an explicit level, the exact message
and the original throwable under the reserved exception context key. Logger
failure propagates; the adapter adds no transport, output or automatic discovery.

Handler\FilteringThrowableHandler($reporter, $predicate) requires a boolean
predicate: true forwards the original once, false skips only that delegate and a
non-boolean raises TypeError. Predicate/delegate failure propagates unchanged. A
filter changes neither recovery nor the public HTTP response policy.

Handler\CallablePhpErrorHandler wraps an explicit four-argument callback and is
injected as ErrorHandler's second argument, or under PhpErrorHandlerInterface in
the application provider. Results, including false/null, are ignored; false is
not native fall-through. The callback owns mask/conversion policy. ErrorException
follows ErrorHandler's reporting boundary; other failures propagate unchanged.
The constructor still takes interface instances in this candidate; bare-callable
inputs remain separate major work. Consult the matching checkout's logging.php,
filtering.php and php-errors.php examples and docs/api.md for complete recipes.

## Wire FastForward definitions

Use `CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider` with
`FastForward\Container\container()`. It implements the Interop provider contract
(`getFactories()` and `getExtensions()`), and resolves
`CoiSA\ErrorHandler\ErrorHandlerInterface`. The usual bootstrap accepts the
provider class and application provider directly:

```php
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;

use function FastForward\Container\container;

$services = container(ErrorHandlerServiceProvider::class, $application);
```

Supply external dependencies and optional `CallableThrowableHandler`/PSR-14
reporters through `$application`. To override an existing library service ID,
such as `CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface`, merge maps with
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

FastForward 1.6/1.8 prioritize the first of separate variadic providers. A duplicate
reporter interface in the application can therefore be shadowed by the library;
placing the application first can still fall back to the library's silent default
after a PSR container error.
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

Middleware requires one scoped object implementing ErrorHandlerInterface and
ThrowableHandlerInterface. It reports downstream failures once, then invokes the
response factory. Reporters remain void and send diagnostics privately. The
provider's GenericThrowableStreamFactory returns a safe body directly, without
reading reporter output. Implement the stream/response interfaces for JSON;
`docs/examples.md#http-rendering` shows a complete pure renderer.

The retained ThrowableStreamFactory is an explicit adapter for public echo-based
templates. Pass a separate public renderer there, never a diagnostic reporter
middleware already called. Only this adapter converts output into the body.
Its renderers must balance removable buffers and never close caller-owned buffers.

## Best-effort delivery

Use Handler\BestEffortThrowableHandlerAggregate only when the application explicitly
wants all fixed reporter slots attempted. It preserves order and original identity;
duplicate slots remain separate attempts. Any failures produce one
Exception\AggregateReportingException after all attempts, even with partial success.
Catch it to retain getOriginalThrowable(), getPrevious() and the ordered getFailures().
Zero/all-success passes return. The original aggregate/defaults remain fail-fast.

No discovery, retry, reentry guard, durable delivery or public redaction is supplied.
The original and reporter failures remain private; middleware propagates a failed
aggregate before response rendering. Native termination can stop remaining slots.
See the matching checkout's examples/best-effort.php and docs/api.md#best-effort-reporting.

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

After a reporter fails during registration, built-in middleware propagates the
failure and restores callbacks without re-rendering through that reporter.
Independent unregistered direct calls remain usable. A fatal inside another PHP
shutdown callback can abort the remaining shutdown queue; do not promise recovery
by re-registering handlers.
Thrown-Throwable handling also does not recover a callback that calls exit/die
or causes a native fatal termination.

For complete runnable recipes covering every built-in adapter, consult
`docs/examples.md` and `docs/index.html#examples` in the matching checkout.
The cookbook retains imports and every executable branch; source parity omits
only the leading license header. Keep its documented setup and bounded verifier.
For full contract details, consult the matching checkout's `docs/fast-forward.md`,
`docs/api.md` and `docs/testing.md`, or the
[source repository](https://github.com/coisa/error-handler). Keep the observed
version, integration changes, executed checks and remaining limits explicit.
