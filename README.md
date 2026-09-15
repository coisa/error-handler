# CoiSA Error Handler

[![PHP Version][ico-php]][link-packagist]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![CI](https://github.com/coisa/error-handler/actions/workflows/ci.yml/badge.svg)](https://github.com/coisa/error-handler/actions/workflows/ci.yml)
[![Total Downloads][ico-downloads]][link-downloads]

Small, composable PHP error handling with PSR-11 containers, PSR-14 events and PSR-15 middleware. Report warnings, handle uncaught throwables and inspect fatal errors at shutdown through the same reporter interface.

**Development documentation for the next major release.** This branch requires PHP 8.3–8.5 and changes lifecycle, CLI exit and HTTP response behavior. These changes are not part of the published 2.0.0 release. Read the [migration guide](docs/migration.md) before adopting this branch.

## Install and run

To reproduce this branch with PHP 8.5 and Composer 2:

```sh
git clone https://github.com/coisa/error-handler.git
cd error-handler
composer install --no-plugins --no-scripts --no-interaction --prefer-dist
composer --no-plugins check
php examples/cli.php
```

While reviewing an unmerged PR, check out its branch before installing. On PHP 8.3/8.4 use `composer update --no-plugins --no-scripts` to resolve compatible development tools. The CLI example deliberately exits **1** after reporting a warning and an uncaught exception.

For the currently published release, `composer require coisa/error-handler` installs the version available on Packagist; it does not select this unreleased work.

## Quick start

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    // Route diagnostics to your application's private logger.
    error_log($error->getMessage());
});
$errors = new ErrorHandler($reporter);
$errors->register();

trigger_error('A reported warning; execution continues.', E_USER_WARNING);
throw new RuntimeException('An uncaught exception; the process exits 1.');
```

Load Composer's autoloader before the snippet. A normal warning is reported and execution continues. Masked/suppressed warnings are ignored. Direct `handleThrowable()` calls do not terminate the process; only the registered uncaught-exception callback does. Reporters are responsible for their output and privacy.

## HTTP integration

Compose `ErrorHandlerMiddleware` with a `ThrowableResponseFactory` and a PSR-17 implementation. The complete, tested [HTTP example](tests/Http/router.php) uses Nyholm PSR-7. Run its bounded smoke test with `php tests/Http/run.php`; it starts and cleans up a loopback-only server automatically.

The middleware restores handlers in `finally`, including when response rendering fails. Reporters that emit no content produce the generic body `Internal Server Error`. A custom renderer may emit a safe response body. Exception text is never used as a reason phrase. Integer exception codes 400–599 become the response status; other codes become 500. Native PHP warnings are reported separately and do not automatically turn a successful request into an error response.

## Documentation

- [API and composition](docs/api.md): lifecycle, factories, handlers, containers and events.
- [Testing and limitations](docs/testing.md): reproducible fire tests, exact observed platforms, isolation and failure boundaries.
- [Migration from 2.0](docs/migration.md): breaking behavior and compatibility changes.
- [Static documentation site](docs/index.html): install, composition and evidence at a glance. Open the file locally, or preview the `docs/` directory with a loopback static server.

GitHub Pages is prepared as a **manual** workflow restricted to `master`. After review and merge, an owner can enable Pages with GitHub Actions as the source and run the Documentation workflow from `master`. Nothing in the pull-request CI publishes documentation or executes PHP on Pages.

## Confidence and boundaries

The test suite includes isolated real PHP failures rather than only calls to mocked handlers. Child processes have memory, time and output bounds; tests check termination, delivery counts and restoration. This is evidence for the exercised cases, not a reliability guarantee for every fatal error, PHP SAPI or downstream reporter. See the [failure matrix](docs/testing.md).

## Credits

- [Felipe Sayão Lobato Abreu][link-author]

## License

Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/coisa/error-handler.svg?style=flat-square
[ico-php]: https://img.shields.io/packagist/php-v/coisa/error-handler.svg?style=flat-square
[ico-license]: https://img.shields.io/github/license/coisa/error-handler.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/coisa/error-handler.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/coisa/error-handler
[link-downloads]: https://packagist.org/packages/coisa/error-handler
[link-author]: https://github.com/coisa
