# Explicit development HTML with Whoops

Read when an application deliberately selects diagnostic HTML in an existing
HTML pipeline. Keep a generic response policy for public requests. This route
does not negotiate `Accept`, infer an environment or enable debug from a header.

## Inspect the selected installation

This route describes the selected unreleased major source of
`coisa/error-handler`, with PHP `^8.3`. Inspect `composer show coisa/error-handler`,
the matching source, PHP version and its autoloader before selecting this route:

```sh
php -r 'require "vendor/autoload.php"; if (!class_exists("CoiSA\\ErrorHandler\\Http\\Message\\WhoopsThrowableResponseFactory")) { throw new RuntimeException("Selected installation lacks the Whoops renderer"); }'
```

The application supplies PSR-17 response/stream factories and
`filp/whoops:^2.18.4`. The patch minimum includes Whoops's PHP 8.5 deprecation
repair. A library development dependency is not installed transitively in a
consumer. Core and unrelated integration remain usable without Whoops.
Renderer classes also exist in published 2.5.0, but this major's middleware
reports before rendering. Class discovery alone does not establish that contract.
Use matching source and skill; do not upgrade or substitute an API automatically.

For an authorized development installation, follow the
[complete setup and program](../examples/README.md#development-html).
Construct
`WhoopsThrowableResponseFactory($responses, $streams, $pageFactory)`, where
`$pageFactory` is a trusted `callable(): Whoops\Handler\PrettyPageHandler`:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Whoops\Handler\PrettyPageHandler;

$html = new WhoopsThrowableResponseFactory(
    $responses,
    $streams,
    /** @return PrettyPageHandler the application's development page */
    static function (): PrettyPageHandler {
        $page = new PrettyPageHandler();

        $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');

        return $page;
    },
);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $html);
```

The complete [program](../examples/whoops.php) includes imports, private reporter,
PSR-17 implementation, request and a named failing route. The application selects
this composition from trusted configuration before processing the request.
The renderer requires Whoops at construction but invokes no page then.
Each render creates a new unregistered `Whoops\Run`, response and body stream.
It configures Whoops to return HTML without emitting headers, writing the page
to output or quitting. It reports nothing and adds no CoiSA output buffer.
The application callback must not emit, terminate or install native callbacks;
the adapter cannot undo arbitrary collaborator effects.

The result has `text/html; charset=UTF-8`, `Cache-Control: no-store`, an integer
throwable status from 400–599 or fallback 500, and the PSR-17 default reason.
Middleware delivers the identical original once before rendering. Reporter
failure prevents rendering; callback/Whoops/PSR-17 failures propagate without
retry. Supply this pure renderer directly. The retained
`ReportingThrowableResponseFactory` is an explicit utility for other pipelines;
adding it here would report the original twice.

## Compose the independent Whoops profile

First verify that the selected installation contains
`ServiceProvider\WhoopsErrorHandlerServiceProvider`, the base
`ServiceProvider\ErrorHandlerServiceProvider` and
`ServiceProvider\Factory\ErrorHandlerMiddlewareFactory`. The application must
run PHP 8.3+; FastForward `^1.10` is required by this source at runtime.

The [complete provider program](../examples/provider-whoops.php) supplies the
PSR-17 interfaces and private reporter through its application provider. Compose
one aggregate in base, Whoops, application order, including for default pages:

```php
$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new WhoopsErrorHandlerServiceProvider(),
    $application,
));
```

Import these names and FastForward's `container` helper as the program does.
Each library provider declares only its own services. The Whoops provider has no
constructor arguments and binds `PrettyPageHandler::class` with `InvokableFactory`.
The renderer's callback resolves that service only during rendering. Resolve and
invoke `ErrorHandlerMiddlewareFactory` for a distinct core/middleware per scope;
direct services remain cached and lookup installs no native callbacks.

To customize the page, configure a `PrettyPageHandler` through its public API
before composing the container and expose it with `ServiceFactory` in the
application map. The configured program uses FastForward Config for a trusted
page title. Application definitions win last inside the aggregate. Sequential
renders reuse the cached configured page: Whoops replaces the exception,
inspector and template data while retaining configuration. The page keeps the
latest diagnostic until its next render; fresh Run/response/body do not imply
that the shared page releases all diagnostic state. A manual callback may instead
create pages. Concurrency and arbitrary subclasses need application tests.

An aggregate fixes binding precedence, not fail-closed construction: FastForward
may autowire a default page after a concrete page factory throws. Preparing a
page before composition does not change that fallback policy. An incompatible
returned page raises `TypeError`; an actual `handle()` failure propagates.
Separate providers can also continue after PSR-11 resolution errors and select
another response definition. Use one aggregate to select the intended profile
and application bindings, then test the actual missing-package and failed-factory
behavior. Do not infer a particular renderer or exception chain from map order.

## Verify diagnostic and delivery limits

Run the direct program and both provider modes against the consumer autoloader.
Check original-object identity and one private report, status and content type,
unchanged caller buffers, fresh response bodies and replacement of sequential
diagnostic data. Check missing dependencies and actual container fallback too.
The programs use real source files, synthetic failures and no HTTP emitter.

Selected field hiding is not comprehensive redaction. Exception messages, trace,
source, causes and other globals may expose secrets. Never select this renderer
for public users merely because they accept HTML. A deployment using it must
retain Whoops in its actual runtime graph even with `--no-dev`.

A separately reproduced limit on PHP 8.5.11 with Whoops 2.18.6 affects throwables
created via native STDIN: Whoops can warn while reading `Standard input code`.
The reporter may receive that distinct PHP warning in addition to the original;
a reporter that throws can interrupt rendering. Do not claim this for all eval
or missing-file traces, suppress the warning or rewrite traces to hide it.
[Issue 99](https://github.com/coisa/error-handler/issues/99) tracks this limit.
Request negotiation remains separate from this renderer.
