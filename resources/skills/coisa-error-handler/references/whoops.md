# Explicit development HTML with Whoops

Read when an application deliberately selects diagnostic HTML in an existing
HTML pipeline. The concrete renderer does not negotiate Accept. The default
provider uses Symfony HTML; Whoops requires an explicit application override.
That application map may retain Accept negotiation, as shown below. No header
or environment name activates debug or selects an unconfigured renderer.

## Inspect the selected installation

This route requires PHP `^8.3`. Inspect `composer show coisa/error-handler`,
the matching source, PHP version and its autoloader before selecting this route:

```sh
php -r 'require "vendor/autoload.php"; if (!class_exists("CoiSA\\ErrorHandler\\Http\\Message\\WhoopsThrowableResponseFactory")) { throw new RuntimeException("Selected installation lacks the Whoops renderer"); }'
```

The application supplies PSR-17 response/stream factories and
`filp/whoops:^2.18.7`. The patch minimum includes Whoops's PHP 8.5 deprecation
and native STDIN/`php -r` source-inspection fixes. A library development dependency
is not installed transitively in a consumer. Composer rejects Whoops versions
below 2.18.7 when selected; core and unrelated integration remain usable without Whoops.
Renderer classes also exist in published 2.5.0, but the middleware described here
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

## Public HTML

`HtmlThrowableResponseFactory($responses, $streams, bool $debug = false)` renders a
production error page with Symfony HtmlErrorRenderer by default. Trusted debug
selects Symfony diagnostics; installing Whoops never changes this default. Public
mode exposes no original exception message, source or trace. The single provider always offers this HTML
route, including without optional Whoops. Its request argument is required;
middleware supplies the current request. See the
[provider public mode](../examples/README.md#interop-html-negotiation).

## Configure the single provider

The [complete provider program](../examples/provider-whoops.php) supplies PSR-17
interfaces and a private reporter through the application provider. Development
modes also explicitly replace the negotiating factory's HTML candidate with
Whoops while retaining JSON and generic candidates:

```php
$services = container($application, new ErrorHandlerServiceProvider(debug: true));
```

This is the same `ServiceProvider\ErrorHandlerServiceProvider` used for ordinary
reporting and JSON. No separate HTML provider is required. Send
`Accept: text/html` on the current request. Debug alone never forces HTML.
The explicit map uses `text/html; charset=UTF-8`, matching Whoops output so
charset-specific Accept preferences and exclusions remain effective.
The default negotiator always uses Symfony HTML. These development modes override
only the HTML candidate with Whoops and never resolve it for a JSON-only request. Direct selection of the concrete renderer still
requires the optional package at construction.

The provider binds `PrettyPageHandler::class` with InvokableFactory. The optional Whoops renderer's lazy
callback resolves that service only during selected HTML rendering.
Resolve and invoke ErrorHandlerMiddlewareFactory for fresh scopes; direct
lookups remain cached and construction installs no callbacks.

Customize the page through its public API before composition and expose it with
ServiceFactory in the application map. Application-first composition selects
that configured service. The program uses trusted Config values for the page
title. Sequential renders retain configuration while replacing current exception,
inspector and template data; the page keeps its latest diagnostic until the next
render. Fresh Whoops Run/response/body do not provide concurrent page isolation.
A manual callback may instead create a page per render.

FastForward may autowire a default page after a failing concrete page factory.
Preparing a page before lookup exposes configuration errors immediately. A merged
provider fixes binding precedence but does not disable concrete fallback.
Wrong-type pages and actual rendering failures retain their runtime failure
contracts. Test application selection and failure behavior rather than infer it
from service discovery.

## Verify diagnostic and delivery limits

Run the direct program and all provider modes against the consumer autoloader.
Check original-object identity and one private report, status and content type,
unchanged caller buffers, fresh response bodies and replacement of sequential
diagnostic data. Check missing dependencies and actual container fallback too.
The programs use real source files, synthetic failures and no HTTP emitter.

Selected field hiding is not comprehensive redaction. Exception messages, trace,
source, causes and other globals may expose secrets. Never select this renderer
for public users merely because they accept HTML. A deployment using it must
retain Whoops in its actual runtime graph even with `--no-dev`.

Every response factory requires the current ServerRequestInterface as its second
method argument. Middleware forwards it automatically; a direct call must supply
it. Negotiation is a separate pure renderer selected by the single provider.
