# Complete application examples

These programs support `coisa/error-handler ^2.4` and PHP 7.4-compatible
syntax. They accept an existing consumer autoloader as their first argument;
they never install dependencies, emit HTTP or contact a real diagnostic service.
Every named class has its own matching file.
The JSON/provider programs additionally require their named APIs in the installed
library. Inspect the selected package first as described in the
[availability checks](../references/json.md#inspect-the-selected-installation);
the common `^2.4` requirement alone does not establish their presence.

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

## Library JSON responses

After verifying the installed `JsonThrowableResponseFactory` and
`ReportingThrowableResponseFactory`, supply an application PSR-17 implementation
such as Nyholm above and run both configurations:

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/json.php ./vendor/autoload.php public
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/json.php ./vendor/autoload.php debug
```

Expect exit 0 and `JSON public: original reported once and explicit response passed`
or the equivalent `JSON debug` line. In a source checkout, replace
`vendor/coisa/error-handler/` with `./`; a copied skill uses its own program path.

The program explicitly selects a boolean configuration, supplies a recording
private reporter and wraps the pure JSON factory with the reporting decorator.
It checks HTTP 503, content type, the exact generic public body or selected
metadata-only debug trace, one identical private report, PSR-17 reason selection
and unchanged output-buffer level. Debug on PHP 7.4 requires ext-json; PHP 8
includes it. The application still owns actual response emission.

## Interop JSON profile

This optional application route needs PHP 8.3+ and FastForward as an application
runtime dependency. It does not change the library core's PHP 7.4 contract:

```sh
composer require 'fast-forward/container:^1.9' 'nyholm/psr7:^1.8' --no-plugins --no-scripts
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-json.php ./vendor/autoload.php public
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/provider-json.php ./vendor/autoload.php debug
```

First inspect that this installed library offers both Interop providers and the
fresh middleware factory. Expect exit 0 and
`Interop JSON public: independent providers and fresh scope passed`, or the
equivalent `Interop JSON debug` line. Use the source/copied-path substitution
above when appropriate.

The application map supplies PSR-17 and its private reporter. JSON precedes the
base provider in the separate-provider call. FastForward Config supplies the
trusted bool before constructing the debug provider; it does not hydrate it.
The program checks the actual reporting renderer, distinct explicit middleware
scopes, one original report and the selected JSON body. Read
[provider precedence and failure behavior](../references/json.md#compose-independent-providers)
before applying required overrides; ordering is not a fail-closed policy.

## Failure and verification boundaries

A missing or invalid autoloader fails before the example starts. Reporter
configuration is checked before constructing the core. Reporter/PSR-17 failures
propagate; the examples do not suppress them or claim success. The library's
PHPUnit suite exercises these application fixtures and failure paths. Production
delivery, a real web server, arbitrary fatal callbacks and an agent host's
activation are outside these programs.
