# Complete application examples

These programs support `coisa/error-handler ^2.4` and PHP 7.4-compatible
syntax. They accept an existing consumer autoloader as their first argument;
they never install dependencies, emit HTTP or contact a real diagnostic service.
Every named class has its own matching file.

## Configured reporting

Install the library in the application with Composer 2:

```sh
composer require 'coisa/error-handler:^2.4' --no-plugins --no-scripts
```

Run from the library source root:

```sh
php resources/skills/coisa-error-handler/examples/configured.php ./vendor/autoload.php
```

From an installed distribution:

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

## Legacy container

Install the optional application implementation compatible with its PHP:

```sh
composer require 'laminas/laminas-servicemanager:^3.0 || ^4.0' --no-plugins --no-scripts
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/container.php ./vendor/autoload.php
```

The source-checkout equivalent replaces `vendor/coisa/error-handler/` with
`./`. Expect exit 0 and
`legacy container: application reporter, caching and fresh scope passed`.

The application parent binds `ThrowableHandlerInterface`, which takes priority
over library definitions. The program checks repeated resolution returns the
cached core while explicit `ErrorHandlerFactory` invocation gives a different
handler. It supplies no implicit registration. See
[container composition](../references/container.md) for ConfigProvider and failure
boundaries.

## Explicit HTTP policy

Supply Nyholm as the application's PSR-17 implementation:

```sh
composer require 'nyholm/psr7:^1.8' --no-plugins --no-scripts
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/http.php ./vendor/autoload.php
```

Expect exit 0 and
`HTTP: private report once and fixed public response passed`.

`PublicThrowableResponseFactory` is a complete application implementation of the
library's response-factory interface. It sends the original to the private
reporter once, then creates status 500, a standard PSR-17 reason and the literal
body `{"error":"Request failed"}`, with JSON content type and `no-store`.
It neither captures output nor copies exception details into the response.
`ThrowingRequestHandler` supplies a synthetic request failure; middleware owns
callback registration/restoration. The URI `http://example.test/` constructs a
request value and is never contacted. Applications own the real emitter and
should test their reporter/PSR-17 failures as well as successful rendering.

## Failure and verification boundaries

A missing or invalid autoloader fails before the example starts. Reporter
configuration is checked before constructing the core. Reporter/PSR-17 failures
propagate; the examples do not suppress them or claim success. The library's
PHPUnit suite exercises these application fixtures and failure paths. Production
delivery, a real web server, arbitrary fatal callbacks and an agent host's
activation are outside these programs.
