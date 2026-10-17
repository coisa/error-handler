# FastForward and Interop composition

Read when an application uses the Interop service provider. The complete
[container program](../examples/container.php) and its
[setup instructions](../examples/README.md#fastforward-container) supply a private
reporter, resolve a cached handler and create a distinct scope. FastForward
Container `^1.10` is required at runtime by the library; do not restore the removed
`ErrorHandlerContainer`, `ConfigProvider` or Laminas metadata.

FastForward 1.10 requires Config `^1.6`; these versions no longer use PHP-DI or
DotAccessData. Inspect the actual locked graph and runtime result; Composer
resolution alone does not establish a warning-free application. Keep diagnostics
under `E_ALL` visible and separate dependency evidence from the library's behavior.

For optional reporter discovery and external PSR implementations, ordinary
composition uses `container(ErrorHandlerServiceProvider::class, $application)`
from `FastForward\Container`. The application is an Interop provider. The library
discovers an available `CallableThrowableHandler` and PSR-14 adapters when their
dispatcher is available. An empty default aggregate is silent. A logger,
filtering reporter or best-effort aggregate is not automatically attached.

The optional callable lookup skips not-found and FastForward's structural
`AutowiringException` when no callback can be inferred. A dispatcher alone still
receives the original throwable and its `ErrorEvent` wrapper. Other construction
failures leave the aggregate factory; in FastForward 1.10.0 an executing provider
factory's failure is wrapped with its cause. The final container may still
autowire and cache an empty concrete aggregate after such a failure. Check actual
deliveries after invocation, and bind `ThrowableHandlerInterface` explicitly for
a required reporter; the default aggregate is not a fail-closed boundary.

For a required reporter or renderer interface override, merge definitions in one
`FastForward\Container\ServiceProvider\AggregateServiceProvider`: library
first, application last, then pass that provider to `container()`. The bundled
program uses `ArrayServiceProvider` and `ServiceFactory` for that application map.
Separate variadic providers prioritize the first successful source and may fall
back after a PSR container error; they do not establish a required override.
An aggregate fixes binding precedence but does not make concrete service
construction fail closed against FastForward's autowiring fallback.

## Select a response profile

The base provider selects `ThrowableResponseFactory` with the pure
`GenericThrowableStreamFactory`. Its optional reporter aggregate can be empty
and silent. A configured private reporter remains an application responsibility.
The independent profiles replace only the selected response services:

- `JsonErrorHandlerServiceProvider` declares its pure JSON renderer and response
  alias. Its optional boolean constructor input enables diagnostic JSON.
- `WhoopsErrorHandlerServiceProvider` declares `PrettyPageHandler`, its pure
  Whoops renderer and response alias. It has no constructor; customize the page
  through the `PrettyPageHandler::class` application service.

For a required reporter and a selected profile, use one aggregate in base,
selected profile, application order. The later profile replaces the base response
alias, and the application supplies its required reporter and PSR-17 bindings.
Library providers do not instantiate or merge another provider. Follow the
complete [JSON](json.md#compose-independent-providers) or
[Whoops](whoops.md#compose-the-independent-whoops-profile) route. Select one
profile explicitly; these providers do not inspect `Accept` or environment values.

Construction and discovery install no PHP callbacks. Repeated
`get(ErrorHandler::class)` returns a cached handler. Resolve
`CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory` and invoke it
with the container for a fresh core; resolve and invoke the corresponding
`ErrorHandlerMiddlewareFactory` for a fresh HTTP scope. Provider-created
middleware has its own handler, separate from the cached process service.
Register only at the actual application boundary and unwind distinct nested
scopes in reverse order.

Supply PSR-17 response and stream implementations. Middleware reports once before
rendering with the selected pure factory; it does not need a reporting decorator.
Keep output capture explicit and do not pass it the private reporter again.
See [HTTP composition](http.md).

FastForward can retry construction and normalize failures to PSR-11 exceptions.
Assert failed required-interface construction, not one factory attempt or an
unchanged exception/cause through every supported container version. This does
not change the reporter's own no-retry behavior. Check precedence, cached and
fresh identities, no registration during lookup, missing implementations and
reporter delivery/failure in the actual application graph.
