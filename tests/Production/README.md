# Alias factory runtime regression

Run `php tests/Production/alias-factory.php /path/to/consumer/vendor/autoload.php`
from the source checkout against an isolated consumer on actual PHP 7.4 and modern
PHP. It verifies exact delegated values, object identity, exception propagation,
legacy container aliases and core handler construction. It does not install PHP
callbacks. Tests are excluded from Composer distribution archives.

Until the separate PSR-11 runtime metadata correction in
[issue #13](https://github.com/coisa/error-handler/issues/13) is merged, the
test application must explicitly require a supported `psr/container` interface
package (`^1.1 || ^2.0`) as well as the library. That explicit fixture dependency
does not prove the library installs PSR-11 transitively. Both fixes are needed for
a complete clean PHP 7.4 production consumer. Use Composer 2 with
`--no-plugins --no-scripts` and no library development dependencies.
