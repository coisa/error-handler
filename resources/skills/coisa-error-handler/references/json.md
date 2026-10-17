# Optional JSON and Interop composition

Read when an application wants the library's JSON renderer or explicitly chooses
Interop/FastForward providers. Keep the ordinary configuration-first setup and
legacy integration available to consumers of the common 2.4 API.

## Inspect the selected installation

JSON rendering, its reporting decorator and the Interop providers were published
in 2.5.0. For an authorized installation selecting these routes, use:

```sh
composer require 'coisa/error-handler:^2.5' --no-plugins --no-scripts
```

The ordinary reporting and legacy integration baseline remains `^2.4`.

Inspect `composer show coisa/error-handler`, its Composer lock source reference
and the actual installed source. Before choosing JSON, verify both classes load
through the application's autoloader:

```sh
php -r 'require "vendor/autoload.php"; foreach (["CoiSA\\ErrorHandler\\Http\\Message\\JsonThrowableResponseFactory", "CoiSA\\ErrorHandler\\Http\\Message\\ReportingThrowableResponseFactory"] as $class) { if (!class_exists($class)) { throw new RuntimeException("Selected installation lacks " . $class); } }'
```

For providers, also verify `ServiceProvider\ErrorHandlerServiceProvider`,
`ServiceProvider\JsonErrorHandlerServiceProvider` and
`ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` under the
`CoiSA\ErrorHandler` namespace. The application needs PHP 8.3+ and
`fast-forward/container:^1.9` in its runtime `require`, not only `require-dev`.
The library core and direct JSON composition retain PHP 7.4-compatible syntax;
debug JSON on PHP 7.4 also needs ext-json. Do not raise an application's PHP
requirement, install a different container or assume all `^2.4` installations
contain these additive symbols. Select a route that the installed source offers.

## Direct JSON responses

Follow the complete [JSON program](../examples/json.php) and its
[setup and expected result](../examples/README.md#library-json-responses).
The application supplies a private reporter and PSR-17 response/stream factories:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;

$json = new JsonThrowableResponseFactory($responses, $streams, $configuration['debug']);

$rendering = new ReportingThrowableResponseFactory($reporter, $json);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $rendering);
```

`$responses` and `$streams` implement the corresponding PSR-17 interfaces;
`$reporter` implements `ThrowableHandlerInterface`; the application configuration
supplies a real bool. These names are application inputs, fully wired in the
program, not automatic library configuration keys.

The JSON factory is pure: it does not report, capture output or emit HTTP.
Integer throwable codes 400–599 select the status; everything else selects 500.
The public body is exactly `{"error":"Request failed"}` with JSON content type.
The response factory chooses its PSR-17 reason instead of using exception text.
The decorator reports the identical original once, then renders. Reporter failure
prevents rendering; renderer/PSR-17 failures propagate without retry. A reporter
that prints still owns that output; use a private sink without public output.

`bool $debug = false` is the JSON factory's third constructor parameter.
Trusted application configuration can enable class, message, file, line and
metadata-only trace. Arguments, objects and previous causes are excluded;
`Cache-Control: no-store` is added and invalid UTF-8 is substituted. Messages and
paths still contain diagnostic data. Choose debug before construction, never
from public request headers or a naive `(bool) 'false'` environment cast.
The default public representation does not require the JSON encoder; requesting
debug without ext-json on PHP 7.4 fails at construction.

Do not decorate legacy capture, which already reports. This decorator belongs
to the 2.x middleware, which does not report independently before rendering;
it must not be copied into a report-before-render major composition.

## Compose independent providers

Use the complete [provider program](../examples/provider-json.php) after its
[application setup](../examples/README.md#interop-json-profile). The application
binds `ResponseFactoryInterface`, `StreamFactoryInterface` and its private
`ThrowableHandlerInterface` in an `ArrayServiceProvider`. Library providers only
declare their own maps; the application composes them explicitly:

```php
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;

use function FastForward\Container\container;

$services = container(
    JsonErrorHandlerServiceProvider::class,
    ErrorHandlerServiceProvider::class,
    $application,
);
```

Separate providers prioritize the first successful definition. JSON precedes
the base provider so its reporting response alias wins over legacy capture.
The base provider alone retains capture defaults and requires the application's
reporter; it does not select a silent aggregate. The JSON provider supplies its
renderer/decorator/response alias, not lifecycle definitions or another provider.

For development configuration, use
`new JsonErrorHandlerServiceProvider($configuration['debug'])` in the same place.
The program reads a boolean through FastForward Config before building the
container. Config supplies values; it does not hydrate the provider or inject
constructor scalars. A strict application entrypoint avoids weak caller coercion.

For an application override of a service already declared by the library, use
one `AggregateServiceProvider` in base, JSON, application order; the last
definition wins within that aggregate. Separate provider fallback and
FastForward autowiring can continue after PSR-11 construction failures, including
a concrete factory failure. Definition precedence is not a guarantee that
configuration errors fail closed. Test the actual selected renderer and failure
behavior with the application's container.

Resolve the reusable `ServiceProvider\Factory\ErrorHandlerMiddlewareFactory`
and invoke it with the composed container for each independent HTTP scope:

```php
$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);

$middleware = $middlewareFactory($services);
```

Import that factory from `CoiSA\ErrorHandler\ServiceProvider\Factory` in the
entrypoint, as the program does. Each invocation constructs a distinct core and
middleware. Direct middleware lookup and the process `ErrorHandlerInterface`
service remain cached. Resolving services installs no callbacks; middleware
owns its bounded request registration/restoration. Fresh objects do not remove
PHP's queued shutdown callbacks or establish a bounded long-running worker policy.

## Verify the application boundary

Run both public/debug programs with the consumer's explicit autoloader. Assert
status, content type, exact generic body or explicit debug metadata, one original
private report, callback restoration and failure propagation. Test provider
order and required overrides; do not infer a correct profile from class discovery
alone. The programs use synthetic fixtures and do not emit or contact a service.
