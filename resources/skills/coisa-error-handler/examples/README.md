# Complete application examples

These programs describe the selected unreleased next-major source of
`coisa/error-handler` and PHP `^8.3`. They do not target the published 2.x API or
assume an available 3.x Composer release. Inspect the consumer's actual source
and autoloader first. They never install dependencies, emit HTTP or contact a
real diagnostic service. Every named class has its own matching file.

Pass the application's existing `vendor/autoload.php` as the first argument.
Programs load their bundled fixtures explicitly; they do not depend on the
library's development autoload map. Commands below use a normal Composer vendor
path. In a source checkout, replace `vendor/coisa/error-handler/` with `./`;
for a copied skill, use that copy's `examples/` path. Keep the application
autoloader argument in either case. A path or version mismatch is a failure,
not permission to install or upgrade the library automatically.

## Configured reporting

Use the application's existing Composer autoloader for this major source. From
the library source root after its documented dependency setup:

```sh
php resources/skills/coisa-error-handler/examples/configured.php ./vendor/autoload.php
```

When the selected source is installed under the normal Composer vendor path:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/configured.php ./vendor/autoload.php
```

A copied skill uses its own `examples/configured.php` with the same application
autoloader. Expect exit 0 and
`configured reporting: original delivered once`.

`config/error-handler.php` selects a `RecordingThrowableHandler`.
`ConfiguredErrorHandlerFactory` accepts interface or callable reporters and
applies flat explicit overrides after that configuration, rejects absent/invalid
reporters and returns a fresh `ErrorHandler`. The program verifies original-object
identity and one delivery for the configured interface and a callable override.
The factory and its keys are application example code, not library APIs.

To integrate in an application, copy those named files into its own source/config
layout, preserve their imports or adjust the namespace and load them through its
existing autoloader. The examples load their own files explicitly so no new
production autoload mapping is needed just to try them. Replace the in-memory
reporter with the application's private reporter. Construction installs no
callbacks; register only at the actual process/request boundary.

## FastForward container

The library requires FastForward `^1.10` at runtime. Run with the selected major
source:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/container.php ./vendor/autoload.php
```

The source-checkout equivalent replaces `vendor/coisa/error-handler/` with
`./`. Expect exit 0 and
`Interop provider: application reporter, caching and fresh scope passed`.

The application maps its required `ThrowableHandlerInterface` to the reporter's
first-class method callable through FastForward's `ServiceFactory`.
One `AggregateServiceProvider` merges library
definitions first and application definitions last, so the explicit reporter wins.
The program checks cached core identity and invokes the reusable
`ServiceProvider\Factory\ErrorHandlerFactory` service for a distinct scope.
Resolution installs no callbacks. See [container composition](../references/container.md)
for ordinary optional discovery and actual failure/fallback boundaries.

## Explicit HTTP policy

Supply Nyholm as the application's PSR-17 implementation if it has not selected
one already:

```sh
composer require 'nyholm/psr7:^1.8.2' --no-plugins --no-scripts
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/http.php ./vendor/autoload.php
```

Expect exit 0 and
`HTTP: private report once and fixed public response passed`.

`PublicThrowableResponseFactory` is a complete, pure application implementation
of the library's response-factory interface. Middleware sends the original to
the private reporter once before this renderer creates status 500, a standard
PSR-17 reason and the literal body `{"error":"Request failed"}`, with JSON
content type and `no-store`. The renderer never reports again, captures output
or copies exception details. This is not a built-in JSON adapter.

`ThrowingRequestHandler` supplies a synthetic request failure; middleware owns
callback registration/restoration. The URI `http://example.test/` constructs a
request value and is never contacted. Applications own the real emitter and
should test reporter/PSR-17 failures as well as successful rendering.

## Library JSON responses

Use the same application PSR-17 implementation as the HTTP program. The built-in
renderer takes the factories and a trusted boolean; no provider is needed:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/json.php ./vendor/autoload.php public
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/json.php ./vendor/autoload.php debug
```

Expect exit 0 and `JSON public: original reported once and explicit response passed`
or `JSON debug: original reported once and explicit response passed`.
The explicit CLI mode represents trusted application configuration, never an
HTTP input. Middleware reports the original once and invokes the pure JSON
renderer directly. The program checks status 503, JSON content type, the exact
public body `{"error":"Request failed"}` or selected diagnostic metadata,
metadata-only trace frames and unchanged caller buffers. Debug adds `no-store`;
it can expose messages and paths. See [JSON policy](../references/json.md).

## Interop JSON profile

Use FastForward from the major's runtime graph and the application's PSR-17
implementation. The program reads a real boolean with FastForward Config before
constructing the provider:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-json.php ./vendor/autoload.php public
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-json.php ./vendor/autoload.php debug
```

Expect exit 0 and
`Interop JSON public: independent providers and fresh scope passed` or
`Interop JSON debug: independent providers and fresh scope passed`.
The application supplies PSR-17 and reporter services, then composes one
`AggregateServiceProvider` in base, JSON, application order. The JSON map selects
the pure renderer; it does not copy base definitions or add a reporting decorator.
The program invokes the reusable middleware factory for a fresh scope, checks
the actual selected renderer, and verifies one report and the requested public
or debug body. FastForward Config supplies application values, not automatic
provider hydration. See [provider precedence](../references/container.md).

## Development HTML

The application explicitly chooses a development HTML pipeline. If this optional
route is authorized and Whoops is not already a runtime dependency, add it to the
application rather than relying on this library's development dependencies:

```sh
composer require 'filp/whoops:^2.18.4' --no-plugins --no-scripts
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/whoops.php ./vendor/autoload.php
```

Use the application's selected PSR-17 implementation as above. Expect exit 0 and
`Whoops HTML: explicit development page and one original report passed`.
The program supplies a trusted page callback, checks that construction invokes
no page, and verifies one private report, status 503, escaped HTML, `no-store` and
unchanged caller buffers. The callback creates/configures the page; middleware
receives the pure renderer directly. No `Accept` header or public environment flag
enables this route. Selected field hiding is not complete redaction.

## Interop Whoops profile

Keep Whoops in the selected application's actual runtime graph, including a
`--no-dev` installation. With the same PSR-17 implementation, run both modes:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-whoops.php ./vendor/autoload.php default
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-whoops.php ./vendor/autoload.php configured
```

Expect exit 0 and
`Interop Whoops default: explicit aggregate, shared page and original reports passed`
or `Interop Whoops configured: explicit aggregate, shared page and original reports passed`.
Both modes compose one aggregate in base, Whoops, application order and invoke
the middleware factory for distinct scopes. The configured mode reads a trusted
page title through FastForward Config, prepares the page before `get()` and binds
it with `ServiceFactory`. The provider itself has no constructor arguments.
Checks cover original-object delivery, statuses, selected renderer, retained
page configuration and replacement of sequential diagnostic data.

The page can be cached and reused sequentially, retaining the latest diagnostic;
the Run, response and body are fresh. Map precedence does not disable concrete
autowiring fallback. See [Whoops composition and limits](../references/whoops.md),
including the native STDIN source-read warning and diagnostic exposure.

## Failure and verification boundaries

A missing or invalid autoloader fails before the example starts. Reporter
configuration is checked before constructing the core. Reporter/PSR-17 failures
propagate; the examples do not suppress them or claim success. The library's
PHPUnit suite exercises these application fixtures and failure paths. Production
delivery, a real web server, arbitrary fatal callbacks and an agent host's
activation are outside these programs. JSON and Whoops programs use the major's
report-before-render contract; adding `ReportingThrowableResponseFactory` to
their middleware would report the original again. The explicit utility remains
available to application pipelines that have not already reported.
