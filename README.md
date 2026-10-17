# CoiSA Error Handler

Report PHP errors and throwables through application-owned handlers. Add native
PHP callbacks, PSR-3 logging, PSR-14 events or PSR-15 HTTP middleware when needed.

[![PHP Version][ico-php]][link-packagist]
[![Composer Package][ico-package]][link-packagist]
[![Tests][ico-build]][link-build]
[![Docs][ico-docs]][link-docs]
[![License][ico-license]](LICENSE)

## 📦 Installation

```sh
composer require coisa/error-handler
```

Use Composer 2 with PHP `^8.3`; CI targets
PHP 8.3–8.5. FastForward Container `^1.10` is a runtime dependency. Applications supply
their logger, dispatcher, HTTP factories and response emitter as needed.

Match the documentation to your installed package and its [release history](CHANGELOG.md).
For an upgrade from 2.x, follow the [migration guide](docs/migration.md) and
compare the [2.x API](https://github.com/coisa/error-handler/tree/v2.5.0).

## 🛠️ Quick start

Create `report.php` beside your application's `vendor/` directory:

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;

require __DIR__ . '/vendor/autoload.php';

$reports = [];

/**
 * Retain the synthetic failure without public diagnostic output.
 *
 * @param Throwable $error the failure to report
 */
$reporter = static function (Throwable $error) use (&$reports): void {
    $reports[] = $error;
};

$errors = new ErrorHandler($reporter);
$failure = new RuntimeException('Example failure');

$errors->handleThrowable($failure);

echo count($reports) . ' report; execution continued' . PHP_EOL;
```

Run `php report.php`; it prints `1 report; execution continued`. Construction
installs no PHP callbacks, and direct reporting returns when the reporter
returns. Reporter failures propagate. Replace the in-memory reporter with your
application's private diagnostic sink.

All three constructor inputs accept the matching handler interface or a callable;
omitted PHP-error and shutdown inputs retain their defaults. Existing interface
objects keep their identity. See the [complete three-callback recipe](examples/php-errors.php).

## 🧰 Choose an integration

| Need | Entry point and guide |
| --- | --- |
| Report a throwable | `ErrorHandler::handleThrowable()` and [reporter composition][link-reporters]. |
| Handle warnings or uncaught exceptions | `register()` / `unregister()` and [native callbacks][link-lifecycle]. |
| Send diagnostics to a logger or dispatcher | [PSR-3 logging][link-logging] and [PSR-14 events][link-events]. |
| Filter or fan out | [Filtering][link-filtering], [aggregates][link-aggregates] and [best effort][link-best-effort]. |
| Use a container | [Interop/FastForward provider, overrides and fresh scopes](docs/fast-forward.md). |
| Return an HTTP response | [PSR-15 middleware and PSR-17 composition](docs/http.md). |
| Return JSON with one private report | [Opt-in JSON rendering](docs/http.md#opt-in-json-responses-and-explicit-reporting) and [complete recipe](examples/http-json.php). |
| Use Interop/FastForward providers | [JSON profile](docs/fast-forward.md#json-profile) and [complete provider recipe](examples/provider-json.php). |
| Render development HTML | [Optional Whoops renderer](docs/http.md#opt-in-development-html-with-whoops) and [manual recipe](examples/http-whoops.php). |
| Configure Whoops services | [Whoops profile](docs/fast-forward.md#development-html-profile) and [complete provider recipe](examples/provider-whoops.php). |

JSON and Whoops adapters introduced in 2.5.0 use the same representation APIs.
Middleware reports once before the pure renderer; do not add the 2.x reporting
decorator. Each provider declares only its own map, composed by the application.

Whoops is an explicit development choice. Applications own trusted debug
activation and format selection.

## Lifecycle and HTTP boundaries

- PHP callbacks are process-wide. Use distinct handlers for nested scopes and
  unregister in reverse order. Only the active owner receives shutdown reporting;
  PHP still retains registered shutdown callbacks until process exit.
- A successfully reported globally uncaught throwable exits with status 1.
  Direct `handleThrowable()` calls return normally. Fatal errors remain fatal.
- FastForward caches services and discovery installs no callbacks. Middleware
  has a separate handler; invoke the reusable factory for each independent scope.
- Middleware reports once, then renders. `GenericThrowableStreamFactory` always
  supplies `Internal Server Error`, independently of reporter output; codes
  400–599 are preserved and reason phrases come from PSR-17. The middleware does
  not capture reporter output: echoed diagnostics can leak outside the PSR-7 body
  and corrupt HTTP emission. JSON and development HTML are explicit renderer choices.
- Output capture is an explicit public-renderer adapter: empty output uses the
  generic body and `"0"` remains valid. Reporters must keep diagnostics private.

See the [HTTP contract](docs/http.md) and [lifecycle guide](docs/api.md) for
failure propagation, callback ownership and output-buffer limits.

## 📚 Guides and examples

- [Complete cookbook](docs/examples.md), [application setup](examples/README.md)
  and [bounded example verification](examples/verify.md).
- [Source documentation](docs/index.html), [web documentation][link-docs] and
  [API reference](docs/api.md).
- [Portable `coisa-error-handler` consumer skill](resources/skills/coisa-error-handler/SKILL.md)
  with complete application examples; [optional installation](docs/agent-skill.md)
  remains a consumer choice.
- [Packagist][link-packagist], [release history](CHANGELOG.md) and
  [repository](https://github.com/coisa/error-handler).

## 🤝 Contributing

Follow the [contributor guide](CONTRIBUTING.md) for dependency setup and
`composer --no-plugins check`. See [testing](docs/testing.md) for process fixtures
and platform evidence, and [Changesets](.changeset/README.md) for release notes.
Node.js is used only by development release tooling.

## License

[MIT](LICENSE), by [Felipe Sayão Lobato Abreu][link-author].

[ico-php]: https://img.shields.io/packagist/php-v/coisa/error-handler?logo=php&logoColor=white
[ico-package]: https://img.shields.io/badge/composer-coisa%2Ferror--handler-F28D1A?logo=composer&logoColor=white
[ico-build]: https://img.shields.io/github/actions/workflow/status/coisa/error-handler/ci.yml?branch=master&logo=githubactions&logoColor=white&label=tests
[ico-docs]: https://img.shields.io/badge/docs-guides-38BDF8?logo=readthedocs&logoColor=white
[ico-license]: https://img.shields.io/badge/license-MIT-64748B

[link-packagist]: https://packagist.org/packages/coisa/error-handler
[link-build]: https://github.com/coisa/error-handler/actions/workflows/ci.yml
[link-docs]: https://coisa.github.io/error-handler/
[link-author]: https://github.com/coisa
[link-reporters]: docs/api.md#lifecycle-and-diagnostic-reporting
[link-lifecycle]: docs/api.md#lifecycle-and-diagnostic-reporting
[link-logging]: docs/api.md#direct-psr-3-logging
[link-events]: docs/api.md#callable-reporters-aggregates-and-psr-14
[link-filtering]: docs/api.md#conditional-reporting
[link-aggregates]: docs/api.md#callable-reporters-aggregates-and-psr-14
[link-best-effort]: docs/api.md#best-effort-reporting
