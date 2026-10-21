# Pure JSON and Interop composition

Read when an application wants the library's JSON renderer or explicitly chooses
the request-aware provider. The library requires PHP `^8.3`; follow the
[migration guide](https://github.com/coisa/error-handler/blob/master/docs/migration.md)
when upgrading from the 2.x middleware composition.

## Inspect the selected installation

Inspect `composer show coisa/error-handler`, its Composer source reference,
PHP version and the actual installed source. Verify the renderer through the
application's autoloader:

```sh
php -r 'require "vendor/autoload.php"; if (!class_exists("CoiSA\\ErrorHandler\\Http\\Message\\JsonThrowableResponseFactory")) { throw new RuntimeException("Selected installation lacks the JSON renderer"); }'
```

For providers, also verify `ServiceProvider\ErrorHandlerServiceProvider`,
`ServiceProvider\Factory\ErrorHandlerMiddlewareFactory` under the
`CoiSA\ErrorHandler` namespace. FastForward `^1.10` is a required runtime dependency
of this source. Class discovery alone does not distinguish the 2.5 renderer APIs
from the request-aware response and middleware contracts described here. Follow the matching
source and skill; do not upgrade a consumer automatically to fit this route.

## Direct JSON responses

Follow the complete [JSON program](../examples/json.php) and its
[setup and expected result](../examples/README.md#library-json-responses).
The application supplies a private reporter and PSR-17 response/stream factories:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;

$serializer = new Symfony\Component\Serializer\Serializer(
    [new Symfony\Component\Serializer\Normalizer\ProblemNormalizer(true)],
    [new Symfony\Component\Serializer\Encoder\JsonEncoder()],
);

$json = new JsonThrowableResponseFactory($responses, $streams, $serializer, $configuration['debug']);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $json);
```

`$responses` and `$streams` implement the corresponding PSR-17 interfaces;
`$reporter` implements `ThrowableHandlerInterface`; the application configuration
supplies a real bool. These names are application inputs, fully wired in the
program, not automatic library configuration keys.

The JSON factory is pure: it does not report, capture output or emit HTTP.
Integer throwable codes 400–599 select the status; everything else selects 500.
The public Problem Details object has `type: about:blank`, `title` from the PSR-17
reason, the selected `status` and `detail: Request failed`. Direct rendering returns
`application/problem+json`; negotiation also accepts `application/json` and keeps
that media type with the same object shape.
The response factory chooses its PSR-17 reason instead of using exception text.
Middleware reports the identical original once, then invokes this pure renderer.
Reporter failure prevents rendering; renderer/PSR-17 failures propagate without
retry. A reporter that prints still owns that output; use a private sink without
public output.

`SerializerInterface $serializer` is required as the third constructor parameter;
the fourth is `bool $debug = false`. Trusted application configuration can enable
original-message `detail`, `class` and Symfony trace metadata. Argument values and
objects are excluded; empty `args` arrays may remain;
`Cache-Control: no-store` is added and invalid UTF-8 is substituted. Messages and
paths still contain diagnostic data. Choose debug before construction, never
from public request headers or a naive `(bool) 'false'` environment cast.
Both modes use the configured serializer. Serialization failures propagate without
an HTML fallback; invalid UTF-8 is substituted. This source requires PHP 8.3+,
where JSON is a core extension.

`ReportingThrowableResponseFactory` remains an explicit reporting utility for
other pipelines. Do not wrap this middleware's JSON renderer with it: middleware
already owns the original report. See [HTTP boundaries](http.md).

## Compose the single provider

Use the complete [provider program](../examples/provider-json.php) after its
[application setup](../examples/README.md#interop-json-negotiation). Bind the
application's PSR-17 factories and private reporter in an `ArrayServiceProvider`,
then supply it first:

```php
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;

use function FastForward\Config\config;
use function FastForward\Container\container;

$settings = config(['error_handler' => ['debug' => false]]);

$services = container(
    $application,
    new ErrorHandlerServiceProvider(debug: $settings->get('error_handler.debug', false)),
);
```

The example Config key belongs to the application, not the library. The default
class-string bootstrap is `container($application, ErrorHandlerServiceProvider::class)`.
The provider supplies lifecycle services and lazy request negotiation; no separate
JSON/Whoops providers are used. Send `Accept: application/json` or `Accept: application/problem+json` on the current
request. JSON-only requests never resolve Whoops, including when debug is true.
Debug controls details, not client representation preferences.

Application-first composition gives normal overrides priority. For a required
interface override that must fail rather than fall through to library defaults,
use one AggregateServiceProvider, library then application. See
[container failure semantics](container.md); a concrete factory can still fall
back to autowiring.

Resolve and invoke `ServiceProvider\\Factory\\ErrorHandlerMiddlewareFactory` for
each fresh HTTP scope. Direct middleware/process lookups remain cached.
Construction installs no callbacks; middleware registers and restores only its
scope. Native callbacks remain process-wide and fresh instances do not provide
fiber/concurrent isolation.

## Verify the application boundary

Run both public/debug programs with the consumer's explicit autoloader. Assert
status, content type, exact generic body or explicit debug metadata, one original
private report, callback restoration and failure propagation. Test provider
order and required overrides; do not infer a correct renderer from class discovery
alone. Every response factory requires the current ServerRequestInterface as its
second method argument; middleware forwards it automatically. The programs use synthetic fixtures and do not emit or contact a service.
