# Pure JSON and Interop composition

Read when an application wants the library's JSON renderer or explicitly chooses
the independent JSON provider. This describes the selected unreleased major
source with PHP `^8.3`, not the published 2.x middleware composition.

## Inspect the selected installation

Inspect `composer show coisa/error-handler`, its Composer source reference,
PHP version and the actual installed source. Verify the renderer through the
application's autoloader:

```sh
php -r 'require "vendor/autoload.php"; if (!class_exists("CoiSA\\ErrorHandler\\Http\\Message\\JsonThrowableResponseFactory")) { throw new RuntimeException("Selected installation lacks the JSON renderer"); }'
```

For providers, also verify `ServiceProvider\ErrorHandlerServiceProvider`,
`ServiceProvider\JsonErrorHandlerServiceProvider` and
`ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` under the
`CoiSA\ErrorHandler` namespace. FastForward `^1.6` is a required runtime dependency
of this source. Class discovery alone does not distinguish the 2.5 renderer APIs
from this major's lifecycle and middleware contracts. Follow the matching
source and skill; do not upgrade a consumer automatically to fit this route.

## Direct JSON responses

Follow the complete [JSON program](../examples/json.php) and its
[setup and expected result](../examples/README.md#library-json-responses).
The application supplies a private reporter and PSR-17 response/stream factories:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;

$json = new JsonThrowableResponseFactory($responses, $streams, $configuration['debug']);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $json);
```

`$responses` and `$streams` implement the corresponding PSR-17 interfaces;
`$reporter` implements `ThrowableHandlerInterface`; the application configuration
supplies a real bool. These names are application inputs, fully wired in the
program, not automatic library configuration keys.

The JSON factory is pure: it does not report, capture output or emit HTTP.
Integer throwable codes 400–599 select the status; everything else selects 500.
The public body is exactly `{"error":"Request failed"}` with JSON content type.
The response factory chooses its PSR-17 reason instead of using exception text.
Middleware reports the identical original once, then invokes this pure renderer.
Reporter failure prevents rendering; renderer/PSR-17 failures propagate without
retry. A reporter that prints still owns that output; use a private sink without
public output.

`bool $debug = false` is the JSON factory's third constructor parameter.
Trusted application configuration can enable class, message, file, line and
metadata-only trace. Arguments, objects and previous causes are excluded;
`Cache-Control: no-store` is added and invalid UTF-8 is substituted. Messages and
paths still contain diagnostic data. Choose debug before construction, never
from public request headers or a naive `(bool) 'false'` environment cast.
The default public representation is a fixed JSON string. Debug uses the JSON
encoder; this source requires PHP 8.3+, where JSON is a core extension.

`ReportingThrowableResponseFactory` remains an explicit reporting utility for
other pipelines. Do not wrap this middleware's JSON renderer with it: middleware
already owns the original report. See [HTTP boundaries](http.md).

## Compose independent providers

Use the complete [provider program](../examples/provider-json.php) after its
[application setup](../examples/README.md#interop-json-profile). The application
binds `ResponseFactoryInterface`, `StreamFactoryInterface` and its private
`ThrowableHandlerInterface` in an `ArrayServiceProvider`. Library providers only
declare their own maps; the application composes them explicitly:

```php
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;

use function FastForward\Container\container;

$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new JsonErrorHandlerServiceProvider(),
    $application,
));
```

The base provider supplies lifecycle services and a generic default response.
The JSON provider supplies only its pure renderer and response alias; it does not
instantiate another provider or import reporter/lifecycle maps. The application
defines the required reporter after the library maps. Without an application
reporter, the base's optional reporter aggregate can be empty and silent.

For development configuration, use
`new JsonErrorHandlerServiceProvider($configuration['debug'])` in the same place.
The program reads a boolean through FastForward Config before building the
container. Config supplies values; it does not hydrate the provider or inject
constructor scalars. A strict application entrypoint avoids weak caller coercion.

For an application override of a service already declared by the library, use
one `AggregateServiceProvider` as above; the last definition wins within that
aggregate. Separate variadic providers prioritize the first successful source,
so the ordinary helper does not establish required overrides. FastForward can
continue after PSR-11 resolution errors or autowire after a concrete factory
failure. Definition precedence is not a guarantee that
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
PHP's queued shutdown callbacks or establish a bounded long-running worker policy;
this core queues at most one shutdown callback per handler instance.

## Verify the application boundary

Run both public/debug programs with the consumer's explicit autoloader. Assert
status, content type, exact generic body or explicit debug metadata, one original
private report, callback restoration and failure propagation. Test provider
order and required overrides; do not infer a correct profile from class discovery
alone. The programs use synthetic fixtures and do not emit or contact a service.
