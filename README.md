# CoiSA Error Handler

[![PHP Version][ico-php]][link-packagist]
[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE)
[![Build Status][ico-build]][link-build]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Total Downloads][ico-downloads]][link-downloads]

## Install

```sh
$ composer require coisa/error-handler
```

See the [changelog](CHANGELOG.md) for published release history.

## Development

The committed development lockfile requires PHP `>=8.4.1 <8.5`.

The CoiSA formatter is a development dependency; installing the library as a
Composer dependency retains its existing PHP `^7.4 || ^8.0` runtime requirement.

Pull requests run the [PHP style workflow](.github/workflows/php-style.yml), which
calls the CoiSA formatter's reusable workflow with PHP 8.4. It installs the locked
development dependencies with Composer plugins and scripts disabled, then checks
only changed PHP files within the existing Finder scope. It does not apply fixes.
For a full local preview, run `composer --no-plugins cs-check`; the new preset also
reports existing formatting differences outside the files changed in a PR.

## Contributing release notes

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
[ico-build]: https://img.shields.io/scrutinizer/build/g/coisa/error-handler/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/coisa/error-handler.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/coisa/error-handler.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/coisa/error-handler.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/coisa/error-handler
[link-build]: https://scrutinizer-ci.com/g/coisa/error-handler
[link-scrutinizer]: https://scrutinizer-ci.com/g/coisa/error-handler/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/coisa/error-handler
[link-downloads]: https://packagist.org/packages/coisa/error-handler
[link-author]: https://github.com/coisa
