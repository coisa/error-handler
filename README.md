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
objects keep their identity. See the [complete three-callback recipe](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php).

## 🧰 Choose an integration

| Need | Entry point and guide |
| --- | --- |
| Report a throwable | `ErrorHandler::handleThrowable()` and [reporter composition][link-reporters]. |
| Format terminal diagnostics | [Symfony CLI handler and Console ownership](docs/cli.md). |
| Handle warnings or uncaught exceptions | `register()` / `unregister()` and [native callbacks][link-lifecycle]. |
| Send diagnostics to a logger or dispatcher | [PSR-3 logging][link-logging] and [PSR-14 events][link-events]. |
| Filter or fan out | [Filtering][link-filtering], [aggregates][link-aggregates] and [best effort][link-best-effort]. |
| Use a container | [Interop/FastForward provider, overrides and fresh scopes](docs/fast-forward.md). |
| Return an HTTP response | [PSR-15 middleware and PSR-17 composition](docs/http.md). |
| Return JSON with one private report | [Opt-in JSON rendering](docs/http.md#opt-in-json-responses-and-explicit-reporting) and [complete recipe](https://github.com/coisa/error-handler/blob/master/examples/http-json.php). |
| Use Interop/FastForward providers | [Request-aware provider](docs/fast-forward.md#request-aware-responses) and [complete provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php). |
| Return public or diagnostic HTML | [Symfony HTML](docs/http.md#public-html) and [complete recipe](https://github.com/coisa/error-handler/blob/master/examples/http-html.php). |
| Select optional Whoops diagnostics | [Development HTML configuration](docs/fast-forward.md#development-html-configuration) and [complete provider recipe](https://github.com/coisa/error-handler/blob/master/examples/provider-whoops.php). |

One Interop provider supplies lifecycle services and request-aware responses.
It selects JSON Problem Details or Symfony HTML when accepted and a generic fallback otherwise. Trusted
debug enables diagnostic HTML/JSON; optional Whoops requires an explicit override. Debug never forces HTML.
Applications supply PSR-17 implementations and a private reporter through
`container($application, ErrorHandlerServiceProvider::class)`.

Every response factory receives the original Throwable and current
ServerRequestInterface. Middleware reports once before rendering; do not add the
2.x reporting decorator. Direct JSON rendering uses Symfony Serializer; JSON and Whoops renderers remain available for
explicit application pipelines.

## Lifecycle and HTTP boundaries

- PHP callbacks are process-wide. Use distinct handlers for nested scopes and
  unregister in reverse order. Only the active owner receives shutdown reporting;
  PHP still retains registered shutdown callbacks until process exit.
- A successfully reported globally uncaught throwable exits with status 1.
  Direct `handleThrowable()` calls return normally. Fatal errors remain fatal.
- FastForward caches services and discovery installs no callbacks. Middleware
  has a separate handler; invoke the reusable factory for each independent scope.
- Middleware reports once, then passes the current request to its pure renderer.
  The default provider negotiates its representation from `Accept`; missing or
  unsupported preferences use the generic `Internal Server Error` body. Codes
  400–599 are preserved and reason phrases come from PSR-17. Reporter echo can
  still leak outside the response and corrupt emission; keep diagnostics private.
- Output capture is an explicit public-renderer adapter: empty output uses the
  generic body and `"0"` remains valid. Reporters must keep diagnostics private.

See the [HTTP contract](docs/http.md) and [lifecycle guide](docs/api.md) for
failure propagation, callback ownership and output-buffer limits.

## 📚 Guides and examples

- [Complete cookbook](docs/examples.md), [application setup](https://github.com/coisa/error-handler/blob/master/examples/README.md)
  and [bounded example verification](https://github.com/coisa/error-handler/blob/master/examples/verify.md).
- [Source documentation](https://github.com/coisa/error-handler/blob/master/docs/index.html), [web documentation][link-docs] and
  [API reference](docs/api.md).
- [Portable `coisa-error-handler` consumer skill](resources/skills/coisa-error-handler/SKILL.md)
  with complete application examples; [optional installation](docs/agent-skill.md)
  remains a consumer choice.
- [Packagist][link-packagist], [release history](CHANGELOG.md) and
  [repository](https://github.com/coisa/error-handler).

## 🤝 Contributing

For suspected vulnerabilities, follow the [security policy](SECURITY.md) and use
the private reporting channel. Ordinary bugs and feature requests belong in issues.

Follow the [contributor guide](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md) for dependency setup and
`composer --no-plugins check`. See [testing](docs/testing.md) for process fixtures
and platform evidence, and the [release process](docs/releases.md) for independent
release notes and reviewed version PRs. Release automation uses the Fast Forward
Changelog reusable workflow without adding a runtime or Node dependency.

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
