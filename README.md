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
composer require 'coisa/error-handler:^2.4' --no-plugins --no-scripts
```

Requires PHP `^7.4 || ^8.0` and Composer 2. Applications supply their logger,
event dispatcher, HTTP factories and response emitter as needed.

This README describes the 2.x API. Check `composer show coisa/error-handler`
and the [release history](CHANGELOG.md) for your installed version.

## 🛠️ Quick start

Create `report.php` beside your application's `vendor/` directory:

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/vendor/autoload.php';

$reports = [];

$reporter = new CallableThrowableHandler(
    /**
     * Retain the synthetic failure without public diagnostic output.
     *
     * @param Throwable $error the failure to report
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error;
    },
);

$errors = new ErrorHandler($reporter);
$failure = new RuntimeException('Example failure');

$errors->handleThrowable($failure);

echo count($reports) . ' report; execution continued' . PHP_EOL;
```

Run `php report.php`; it prints `1 report; execution continued`. Construction
installs no PHP callbacks, and direct reporting returns when the reporter
returns. Reporter failures propagate. Replace the in-memory reporter with your
application's private diagnostic sink.

## 🧰 Choose an integration

| Need | Entry point and guide |
| --- | --- |
| Report a throwable | `ErrorHandler::handleThrowable()` and [reporter composition][link-reporters]. |
| Handle warnings or uncaught exceptions | `register()` / `unregister()` and [native callbacks][link-lifecycle]. |
| Send diagnostics to a logger or dispatcher | [PSR-3 logging][link-logging] and [PSR-14 events][link-events]. |
| Filter or fan out | [Filtering][link-filtering], [aggregates][link-aggregates] and [best effort][link-best-effort]. |
| Use a PSR-11 or Laminas application | [`ErrorHandlerContainer`, `ConfigProvider` and factories](docs/container.md). |
| Return an HTTP response | [PSR-15 middleware and PSR-17 composition](docs/http.md). |
| Return JSON with one private report | [Opt-in JSON rendering](docs/http.md#opt-in-json-responses-and-explicit-reporting) and [complete recipe](examples/http-json.php). |
| Use Interop/FastForward providers | [Optional JSON profile](docs/container.md#optional-interop-and-fastforward-providers) and [complete provider recipe](examples/provider-json.php). |
| Render development HTML | [Optional Whoops renderer](docs/http.md#opt-in-development-html-with-whoops) and [manual recipe](examples/http-whoops.php). |
| Configure Whoops services | [Optional Whoops profile](docs/container.md#development-html-profile) and [complete provider recipe](examples/provider-whoops.php). |

JSON/Whoops response adapters and Interop providers were published in 2.5.0;
select `coisa/error-handler:^2.5` for those routes and verify the installed source.
The bundled skill keeps its common `^2.4` baseline. FastForward examples require
application PHP 8.3+, PSR-17 factories and a private reporter; core and legacy
integrations retain PHP 7.4 support.

Whoops is an explicit development choice. Applications own trusted debug
activation and format selection.

## Lifecycle and HTTP boundaries

- PHP callbacks are process-wide. Use distinct handlers for nested scopes and
  unregister in reverse order. Shutdown callbacks remain queued after
  `unregister()`; repeated cycles are not a bounded worker scope manager.
- Reporting does not recover a fatal error or guarantee a nonzero exit for an
  uncaught exception. Applications own their process exit policy.
- The built-in container caches handlers and starts with an empty reporter
  aggregate. Configure diagnostics explicitly; a fresh middleware wrapper can
  still share its cached handler. Service resolution installs no callbacks.
- The built-in HTTP stream factory captures reporter output. Empty output or
  `"0"` falls back to exception text, and the response factory retains valid
  exception messages as reason phrases. A silent private logger therefore does
  not produce a safe public response. Use the
  [explicit application response recipe](docs/http.md#a-safe-application-response)
  for a private report and fixed public body. The application emits the response.

See the [HTTP contract](docs/http.md#existing-response-and-output-capture) for
status-code mapping, reason validation and output-buffer cleanup limits.

## 📚 Guides and examples

- [Complete 2.x cookbook](docs/examples.md), [application setup](examples/README.md)
  and [bounded example verification](examples/verify.md).
- [Published documentation][link-docs] and [API reference](docs/api.md).
- [Portable `coisa-error-handler` consumer skill](resources/skills/coisa-error-handler/SKILL.md)
  with complete application examples; [optional installation](docs/agent-skill.md)
  remains a consumer choice.
- [Packagist][link-packagist], [release history](CHANGELOG.md) and
  [repository](https://github.com/coisa/error-handler).

## 🤝 Contributing

For suspected vulnerabilities, follow the [security policy](SECURITY.md) and use
the private reporting channel. Ordinary bugs and feature requests belong in issues.

Follow the [contributor guide](CONTRIBUTING.md) for dependency setup and
`composer --no-plugins check`. See [testing](docs/testing.md) for process fixtures
and platform evidence, and the [release process](docs/releases.md) for independent
release notes and reviewed version PRs. Release automation uses the Fast Forward
Changelog reusable workflow without adding a runtime or Node dependency.

## License

[MIT](LICENSE), by [Felipe Sayão Lobato Abreu][link-author].

[ico-php]: https://img.shields.io/packagist/php-v/coisa/error-handler?logo=php&logoColor=white
[ico-package]: https://img.shields.io/badge/composer-coisa%2Ferror--handler-F28D1A?logo=composer&logoColor=white
[ico-build]: https://img.shields.io/github/actions/workflow/status/coisa/error-handler/ci.yml?branch=master&logo=githubactions&logoColor=white&label=tests
[ico-docs]: https://img.shields.io/badge/docs-2.x-38BDF8?logo=readthedocs&logoColor=white
[ico-license]: https://img.shields.io/badge/license-MIT-64748B

[link-packagist]: https://packagist.org/packages/coisa/error-handler
[link-build]: https://github.com/coisa/error-handler/actions/workflows/ci.yml
[link-docs]: https://coisa.github.io/error-handler/
[link-author]: https://github.com/coisa
[link-reporters]: docs/api.md#choose-a-reporter-and-scope
[link-lifecycle]: docs/api.md#native-callbacks
[link-logging]: docs/api.md#direct-psr-3-logging
[link-events]: docs/api.md#events-and-logging
[link-filtering]: docs/api.md#conditional-reporting
[link-aggregates]: docs/api.md#aggregation-and-every-built-in-adapter
[link-best-effort]: docs/api.md#best-effort-reporting
