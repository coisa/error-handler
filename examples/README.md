# Executable 2.x cookbook

These source-checkout examples use synthetic data and PHP 7.4-compatible syntax. They target the existing 2.x API, not the proposed major. Verify the installed package/checkouts and pending patch boundaries in the [API guide](../docs/api.md).

Read all ten complete recipes, including imports and every executable branch, in the [2.x cookbook](../docs/examples.md). Its displayed code is checked against these sources; setup and bounded execution remain documented here and in the verifier guide.

## Application dependencies

The library needs Composer's autoloader. The HTTP, event and container examples additionally use application implementations. In a disposable application directory, install:

```sh
composer require 'coisa/error-handler:^2.0' 'nyholm/psr7:^1.8' 'phly/phly-event-dispatcher:^1.0' 'laminas/laminas-servicemanager:^3.0 || ^4.0' --no-plugins --no-scripts --no-interaction
```

Choose a library release/check-out containing the metadata and PHP 7.4 alias repairs before running legacy container examples on PHP 7.4. When testing source maintenance, use a local path repository for that exact source instead of silently resolving a published version. Keep its vendor/cache/lock state in the disposable application; do not modify the library's Composer requirements.

Each implementation resolves to a version compatible with the application's actual PHP. Modern Laminas development dependencies can require newer PHP than the library's runtime; do not bypass platform requirements to test PHP 7.4.

The direct logging recipe requires a checkout or release containing `LogThrowableHandler`; older installed releases may not provide this opt-in adapter. Its recording logger needs no transport or extra package beyond the library's existing PSR-3 dependency.

Individual recipes need only their application dependencies. The bounded
verifier additionally uses Symfony Process as a development tool. Add it in the
disposable application before selecting that application's autoloader:

```sh
composer require --dev 'symfony/process:^5.4 || ^6.4 || ^7.4 || ^8.0' --no-plugins --no-scripts --no-interaction
```

Then use the disposable application's dependencies:

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
| [php-errors.php](php-errors.php) | Explicit CallablePhpErrorHandler injection; four unchanged arguments and application-owned mask/conversion policy without native registration. |
| [filtering.php](filtering.php) | Opt-in FilteringThrowableHandler composition; PHP severity selection, nonmatch routing and original-object identity alongside a general diagnostic branch. |
| [best-effort.php](best-effort.php) | Opt-in ordered reporting through partial success; one aggregate exception retains the original and all reporter failures after every attempt. Requires a source version containing BestEffortThrowableHandlerAggregate and AggregateReportingException. |
| [events.php](events.php) | DispatchThrowableHandler, DispatchErrorEventThrowableHandler, ErrorEvent, both callable listeners/providers and LogErrorEventListener with an application PSR-3 logger. |
| [container.php](container.php) | Legacy ErrorHandlerContainer, ConfigProvider/Laminas maps, an application reporter, cached services and fresh ErrorHandlerFactory invocation. |
| [http.php](http.php) | PSR-15/PSR-17 response composition, actual output capture/empty/zero fallback, an explicit safe application renderer and callback restoration after failure. |

Run direct/logging/adapter/event/container recipes individually with `php examples/<file>.php`. Run process/fatal/HTTP scenarios through `php examples/verify.php` to preserve the documented bounds and observed exit/output checks. The verifier also covers default fatal-shutdown reporting through PhpLastErrorShutdownHandler.

Examples return response objects and synthetic log records. They do not deliver real logs, start a web server, emit HTTP to a client or certify an application's transport. Applications own those integrations.

The HTTP example's valid single-line reason remains unchanged by the [reason-phrase repair](https://github.com/coisa/error-handler/issues/89). With that repair present, forbidden controls select the PSR-17 default reason; the original throwable and capture/empty/zero body behavior remain unchanged. See [the HTTP reason contract](../docs/http.md#existing-response-and-output-capture) before emitting responses.

Continue with [core API](../docs/api.md), [container composition](../docs/container.md), and [HTTP behavior](../docs/http.md).
