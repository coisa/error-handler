# Complete application examples

These programs describe the selected unreleased next-major source of
`coisa/error-handler` and PHP `^8.3`. They do not target the published 2.x API or
assume an available 3.x Composer release. Inspect the consumer's actual source
and autoloader first. They never install dependencies, emit HTTP or contact a
real diagnostic service. Every named class has its own matching file.

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
`ConfiguredErrorHandlerFactory` applies flat explicit overrides after that
configuration, rejects absent/invalid reporters and returns a fresh
`ErrorHandler`. The program verifies original-object identity and one delivery.
The factory and its keys are application example code, not library APIs.

To integrate in an application, copy those named files into its own source/config
layout, preserve their imports or adjust the namespace and load them through its
existing autoloader. The examples load their own files explicitly so no new
production autoload mapping is needed just to try them. Replace the in-memory
reporter with the application's private reporter. Construction installs no
callbacks; register only at the actual process/request boundary.

## FastForward container

The library requires FastForward at runtime. Run with the selected major source:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/container.php ./vendor/autoload.php
```

The source-checkout equivalent replaces `vendor/coisa/error-handler/` with
`./`. Expect exit 0 and
`Interop provider: application reporter, caching and fresh scope passed`.

The application maps its required `ThrowableHandlerInterface` through
FastForward's `ServiceFactory`. One `AggregateServiceProvider` merges library
definitions first and application definitions last, so the explicit reporter wins.
The program checks cached core identity and invokes the reusable
`ServiceProvider\Factory\ErrorHandlerFactory` service for a distinct scope.
Resolution installs no callbacks. See [container composition](../references/container.md)
for ordinary optional discovery and actual failure/fallback boundaries.

## Explicit HTTP policy

Supply Nyholm as the application's PSR-17 implementation if it has not selected
one already:

```sh
composer require 'nyholm/psr7:^1.8' --no-plugins --no-scripts
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

## Failure and verification boundaries

A missing or invalid autoloader fails before the example starts. Reporter
configuration is checked before constructing the core. Reporter/PSR-17 failures
propagate; the examples do not suppress them or claim success. The library's
PHPUnit suite exercises these application fixtures and failure paths. Production
delivery, a real web server, arbitrary fatal callbacks and an agent host's
activation are outside these programs.
