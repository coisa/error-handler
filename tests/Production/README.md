# Alias factory runtime regression

Run `php tests/Production/alias-factory.php /path/to/consumer/vendor/autoload.php`
from the source checkout against an isolated consumer on actual PHP 7.4 and modern
PHP. It verifies exact delegated values, object identity, exception propagation,
legacy container aliases and core handler construction. It does not install PHP
callbacks. Tests are excluded from Composer distribution archives.

The consumer must require only `coisa/error-handler`; its runtime metadata must
install `psr/container` transitively. Use Composer 2 with
`--no-plugins --no-scripts` and no library development dependencies. The
[PHP CI workflow](../../.github/workflows/ci.yml) runs this fixture together with
`container.php` and the distribution check in `archive.php`, using current
runtime dependency resolutions on PHP 7.4 and 8.0–8.5 and lowest resolutions on
PHP 7.4 and 8.5. See [contributor checks](../../CONTRIBUTING.md) for local commands
and the distinction between production and development runners.
