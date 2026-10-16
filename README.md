# CoiSA Error Handler

[![PHP Version][ico-php]][link-packagist]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![PHP CI][ico-build]][link-build]
[![Total Downloads][ico-downloads]][link-downloads]

## Install

```sh
$ composer require coisa/error-handler
```

See the [changelog](CHANGELOG.md) for published release history.

## Throwable response codes

`ThrowableResponseFactory` uses integer exception codes from 400 through 500
inclusive as HTTP status codes. Other integers and string codes, including PDO
SQLSTATE values and numeric-looking strings, fall back to status 500. SQLSTATE
values are not interpreted as HTTP statuses.

The response retains the exception message as its reason phrase. Its body comes
from the configured `ThrowableStreamFactoryInterface`, which receives the same
throwable instance. Applications control the content produced by that factory.

## Use the 2.x API

The library declares PHP `^7.4 || ^8.0`. Applications own reporters, PSR
implementations, HTTP emission and when native PHP callbacks are installed.
These guides describe the existing 2.x API; a pending Changeset is not a published
Composer release. Check the installed version and its matching source before
using a maintenance fix or instructions from a different major.

After loading Composer's autoloader and importing `CoiSA\ErrorHandler\ErrorHandler`
and `CoiSA\ErrorHandler\Handler\CallableThrowableHandler`, explicit reporting needs
no global registration:

<!-- example: examples/direct.php -->
```php
$errors = new ErrorHandler(new CallableThrowableHandler(
    /**
     * Print only the synthetic message used by this executable example.
     *
     * @param Throwable $error the synthetic failure to report
     */
    static function (Throwable $error): void {
        echo 'reported: ' . $error->getMessage() . PHP_EOL;
    },
));

$errors->handleThrowable(new RuntimeException('Example failure'));

echo 'continued' . PHP_EOL;
```

The complete [direct example](https://github.com/coisa/error-handler/blob/master/examples/direct.php)
prints a synthetic diagnostic, then continues. Use an appropriate private sink in
a real application. Read the guide for your integration:

- [Core API, callbacks, adapters and events/logging](docs/api.md).
- [Opt-in direct PSR-3 logging](docs/api.md#direct-psr-3-logging), an additive minor under development; verify that the selected checkout/release contains `LogThrowableHandler`.
- [Opt-in conditional reporting](docs/api.md#conditional-reporting) and its [runnable filtering example](examples/filtering.php).
- [Legacy container, ConfigProvider and application dependencies](docs/container.md).
- [HTTP middleware, output capture/fallback and safe application rendering](docs/http.md).
- [Runnable cookbook](https://github.com/coisa/error-handler/blob/master/examples/README.md)
  and its [bounded verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md).
- [Portable coisa-error-handler consumer skill](https://github.com/coisa/error-handler/blob/master/.agents/skills/coisa-error-handler/SKILL.md),
  retaining source identity when copied into a project; no global installation is required.

The optional [static HTML guide source](https://github.com/coisa/error-handler/blob/master/docs/index.html)
can be opened locally. Its workflow validates source excerpts in pull requests;
Pages deployment requires a separate owner-authorized manual run.

The original 2.x runtime metadata and PHP 7.4 alias defects are tracked in
[#13](https://github.com/coisa/error-handler/issues/13) and
[#40](https://github.com/coisa/error-handler/issues/40). Use a checkout or published
patch containing both for a clean PHP 7.4 container installation. The proposed
major's FastForward provider, pure default renderer and changed exit policy are
not existing 2.x APIs.

## Development

Development tools are installed through Composer's development dependencies and
are not required to use the library. Dependency resolution produces an ignored
local lockfile, as described in the contributor guide below.

Pull requests run the [PHP style workflow](https://github.com/coisa/error-handler/blob/master/.github/workflows/php-style.yml), which
calls the CoiSA formatter's reusable workflow. It installs development
dependencies with Composer plugins and scripts disabled, then checks
only changed PHP files within the existing Finder scope. It does not apply fixes.
For a full local preview, run `composer --no-plugins cs-check`; the new preset also
reports existing formatting differences outside the files changed in a PR.

See [testing and characterization](docs/testing.md) for bounded failure probes,
loopback HTTP fixtures, supported commands and known 2.x limitations.

## Contributing release notes

See [contributor dependency setup](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md) for local Composer resolution
and the policy for library and application lockfiles.

Maintainers use [Changesets](https://github.com/coisa/error-handler/blob/master/.changeset/README.md)
to record changes and review version PRs. Merging a generated version PR starts
Git tag and GitHub release publication. Node.js is needed only for this
development tooling.

## Credits

- [Felipe Sayão Lobato Abreu][link-author]

## License

Please see [License File](LICENSE) for more information.

[ico-version]: https://img.shields.io/packagist/v/coisa/error-handler.svg?style=flat-square
[ico-php]: https://img.shields.io/packagist/php-v/coisa/error-handler.svg?style=flat-square
[ico-license]: https://img.shields.io/github/license/coisa/error-handler.svg?style=flat-square
[ico-build]: https://github.com/coisa/error-handler/actions/workflows/ci.yml/badge.svg?branch=master
[ico-downloads]: https://img.shields.io/packagist/dt/coisa/error-handler.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/coisa/error-handler
[link-build]: https://github.com/coisa/error-handler/actions/workflows/ci.yml
[link-downloads]: https://packagist.org/packages/coisa/error-handler
[link-author]: https://github.com/coisa
