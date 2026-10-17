# Alias factory runtime regression

Run `php tests/Production/alias-factory.php /path/to/consumer/vendor/autoload.php`
from the source checkout against an isolated consumer on actual PHP 7.4 and modern
PHP. It verifies exact delegated values, object identity, exception propagation,
legacy container aliases and core handler construction. It does not install PHP
callbacks. Tests are excluded from Composer distribution archives.

`container.php` also constructs the optional Interop provider objects without
requesting their definitions. Their interface contract loads from runtime
metadata on PHP 7.4 without FastForward; using their service maps remains an
opt-in PHP 8.3+ application integration.

The consumer must require only `coisa/error-handler`; its runtime metadata must
install `psr/container` transitively. Use Composer 2 with
`--no-plugins --no-scripts` and no library development dependencies. The
[PHP CI workflow](../../.github/workflows/ci.yml) runs this fixture together with
`container.php` and the distribution check in `archive.php`, using current
runtime dependency resolutions on PHP 7.4 and 8.0–8.5 and lowest resolutions on
PHP 7.4 and 8.5. See [contributor checks](../../CONTRIBUTING.md) for local commands
and the distinction between production and development runners.

The distribution check admits only root metadata, runtime source, useful guides
and `resources/skills/coisa-error-handler/`. Every runtime and canonical skill
file must be present with bytes matching source; arbitrary `resources/` content
and development directories remain forbidden. The skill's examples and license
are consumer assets and must survive archive exclusions.
