# Executable 2.x cookbook

These source-checkout examples use synthetic data and PHP 7.4-compatible syntax. They target the existing 2.x API, not the proposed major. Verify the installed package/checkouts and pending patch boundaries in the [API guide](../docs/api.md).

## Application dependencies

The library needs Composer's autoloader. The HTTP, event and container examples additionally use application implementations. In a disposable application directory, install:

```sh
composer require 'coisa/error-handler:^2.0' 'nyholm/psr7:^1.8' 'phly/phly-event-dispatcher:^1.0' 'laminas/laminas-servicemanager:^3.0 || ^4.0' --no-plugins --no-scripts --no-interaction
```

Choose a library release/check-out containing the metadata and PHP 7.4 alias repairs before running legacy container examples on PHP 7.4. When testing source maintenance, use a local path repository for that exact source instead of silently resolving a published version. Keep its vendor/cache/lock state in the disposable application; do not modify the library's Composer requirements.

Each implementation resolves to a version compatible with the application's actual PHP. Modern Laminas development dependencies can require newer PHP than the library's runtime; do not bypass platform requirements to test PHP 7.4.

The direct logging recipe requires a checkout or release containing `LogThrowableHandler`. It is an additive minor change under development; the pending Changeset does not make that API available in an older installed release. Its recording logger needs no transport or extra package beyond the library's existing PSR-3 dependency.

To use the disposable application's dependencies:

```sh
ERROR_HANDLER_AUTOLOAD=/absolute/application/vendor/autoload.php php examples/verify.php
```

Without that variable, bootstrap uses the source checkout's `vendor/autoload.php`. Loading bootstrap installs no callbacks. The verifier runs every scenario in bounded children; see [its operating guide](verify.md).

## Recipes

| Recipe | What it executes |
| --- | --- |
| [direct.php](direct.php) | Explicit ErrorHandler construction and CallableThrowableHandler reporting; execution continues. |
| [logging.php](logging.php) | Explicit LogThrowableHandler composition, exact/empty messages, configured level and original exception context; no transport or callback registration. |
| [process.php](process.php) | Warning handling, scoped restoration, registered uncaught behavior and CallableShutdownHandler. The fatal mode is verifier-only. |
| [handlers.php](handlers.php) | Callable adapters, aggregate attach/order/failure propagation, ThrowErrorExceptionPhpErrorHandler, suppression and PhpLastErrorShutdownHandler's no-fatal path. |
| [events.php](events.php) | DispatchThrowableHandler, DispatchErrorEventThrowableHandler, ErrorEvent, both callable listeners/providers and LogErrorEventListener with an application PSR-3 logger. |
| [container.php](container.php) | Legacy ErrorHandlerContainer, ConfigProvider/Laminas maps, an application reporter, cached services and fresh ErrorHandlerFactory invocation. |
| [http.php](http.php) | PSR-15/PSR-17 response composition, actual output capture/empty/zero fallback, an explicit safe application renderer and callback restoration after failure. |

Run direct/logging/adapter/event/container recipes individually with `php examples/<file>.php`. Run process/fatal/HTTP scenarios through `php examples/verify.php` to preserve the documented bounds and observed exit/output checks. The verifier also covers default fatal-shutdown reporting through PhpLastErrorShutdownHandler.

Examples return response objects and synthetic log records. They do not deliver real logs, start a web server, emit HTTP to a client or certify an application's transport. Applications own those integrations.

Continue with [core API](../docs/api.md), [container composition](../docs/container.md), and [HTTP behavior](../docs/http.md).
