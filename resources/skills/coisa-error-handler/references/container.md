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
composition uses `container($application, ErrorHandlerServiceProvider::class)`
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

## Request-aware responses

The single `ErrorHandlerServiceProvider(bool $debug = false, bool $cli = false)` selects
`NegotiatingThrowableResponseFactory` by default. It uses the current request's
Accept header, with generic, JSON and Symfony HTML candidates. Trusted debug enables diagnostics
in JSON and Symfony HTML. Optional Whoops requires an explicit application override.
JSON-only requests never resolve Whoops. Debug permits diagnostic HTML, but does not force it;
without Accept or a supported positive match, the generic response is used.
A configured private reporter remains the application's responsibility.

Use the complete [JSON](json.md#compose-the-single-provider) or
[Whoops](whoops.md#configure-the-single-provider) program. The ordinary bootstrap
is `container($application, ErrorHandlerServiceProvider::class)`.
Enable diagnostics with a real boolean in
`new ErrorHandlerServiceProvider(debug: $debug)`, read from trusted application
configuration. No specific configuration key is read by the library.

Explicit `cli: true` appends Symfony terminal formatting to the default reporter
aggregate; it does not select HTTP diagnostics. An application OutputInterface
service supplies an optional Console destination; otherwise it uses stderr.
Keep one Console exception owner; see [terminal diagnostics](reporters.md#symfony-terminal-diagnostics).

The provider also declares concrete public HTML, JSON and Whoops renderers and a lazy cached
`PrettyPageHandler` service. Supply that page through the application map to
customize it. The removed separate JSON/Whoops providers are not needed.
Use a single merged provider only for the required-interface failure contract
described above.

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
