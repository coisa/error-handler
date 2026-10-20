# Explicit development HTML with Whoops

Read when an application deliberately selects diagnostic HTML in an existing
HTML pipeline. Keep a generic response policy for public requests. This route
does not negotiate `Accept`, infer an environment or enable debug from a header.

## Inspect the selected installation

The Whoops renderer and Interop profile were published in 2.5.0 and require
`coisa/error-handler ^2.5`. The common `^2.4` baseline does not establish their
availability. Inspect `composer show coisa/error-handler`, the matching installed
source and its autoloader before selecting this route:

```sh
php -r 'require "vendor/autoload.php"; foreach (["CoiSA\\ErrorHandler\\Http\\Message\\WhoopsThrowableResponseFactory", "CoiSA\\ErrorHandler\\Http\\Message\\ReportingThrowableResponseFactory"] as $class) { if (!class_exists($class)) { throw new RuntimeException("Selected installation lacks " . $class); } }'
```

The application supplies PSR-17 response/stream factories and
`filp/whoops:^2.18.7`. The patch minimum includes Whoops's PHP 8.5 deprecation
and native STDIN/`php -r` source-inspection repairs. A library development dependency is not installed transitively in a
consumer. Composer rejects Whoops versions below 2.18.7 when that package is
selected; it does not require Whoops for core or unrelated integration.
Use the installation's own supported route when these APIs are absent; do not
upgrade or substitute another API automatically.

For an authorized development installation, follow the
[complete setup and program](../examples/README.md#development-html).
Direct construction retains PHP 7.4-compatible syntax. Construct
`WhoopsThrowableResponseFactory($responses, $streams, $pageFactory)`, where
`$pageFactory` is a trusted `callable(): Whoops\Handler\PrettyPageHandler`:

```php
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

$rendering = new ReportingThrowableResponseFactory($reporter, $html);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $rendering);
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
The decorator delivers the identical original once before rendering for the 2.x
middleware, which does not report independently first. Reporter failure prevents
rendering; callback/Whoops/PSR-17 failures propagate without retry. Do not decorate
legacy capture, which already reports. A report-before-render middleware needs
the pure renderer directly rather than another reporting decorator.

## Compose the independent Whoops profile

First verify that the selected installation contains
`ServiceProvider\WhoopsErrorHandlerServiceProvider`, the base
`ServiceProvider\ErrorHandlerServiceProvider` and
`ServiceProvider\Factory\ErrorHandlerMiddlewareFactory`. The application must
run PHP 8.3+ and require `fast-forward/container:^1.9` at runtime.

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
Separate Whoops/base/application providers can fall back to reporting around
legacy capture without Whoops and report the original twice. Use the one-aggregate
route, which preserves the missing-package cause/hint in the verified consumer.

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

Native STDIN and `php -r` frames keep their original virtual source names with
the supported Whoops version. Rendering does not add source-read warnings for
those names or alter the original trace. Legitimate application warnings retain
their configured PHP-error reporting policy; no warning suppression is installed.
Request negotiation remains separate from this renderer.
