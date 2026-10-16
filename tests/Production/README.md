# Production provider regressions

Run these entrypoints from the source checkout against a disposable Composer
consumer requiring only `coisa/error-handler`, with no library development
dependencies:

```sh
php tests/Production/container.php /path/to/consumer/vendor/autoload.php
php tests/Production/alias-factory.php /path/to/consumer/vendor/autoload.php
```

The unreleased major declares PHP `^8.3`. The intended native CI matrix is PHP
8.3–8.5 with the documented dependency bounds; this is a tested range, not an
artificial 8.5 upper constraint. Resolve with the actual PHP runtime and Composer 2
using `--no-dev --no-plugins --no-scripts`. The runtime graph supplies FastForward,
Interop and the PSR interfaces. No Nyholm, application logger or other incidental
consumer dependency is needed for these construction/reporting checks.

`container.php` uses the real Interop provider and FastForward container. It
checks default aliases, cached identities, distinct explicit core scopes and
fresh middleware wrappers, then reports synthetic events through the configured
application reporter. The PSR-17 construction-only sentinels throw if lookup tries
to render, open a file or consume a resource; they are not response implementations.
Temporary surrounding PHP callbacks are restored, and lookup must leave them
unchanged. Nested middleware lifecycle/rendering is covered separately by the
functional tests; fresh wrapper identity alone does not prove a fresh handler.

`alias-factory.php` retains its historical CI entrypoint name but now tests the
major integration: application-required interface overrides are merged after
library definitions in one real `AggregateServiceProvider`. It verifies exact
reporter/core identities, original-event delivery and failure instead of silent
fallback when required construction fails. FastForward may retry resolution and
normalize failures to PSR11 exceptions; this fixture does not promise a single
construction attempt or unchanged failure identity through that dependency.
The removed package `AliasFactory` and PHP 7.4 repair are not restored or retested.

The independent `archive.php` check verifies the committed distribution before
consumer installation. Tests are export-ignored and never shipped as runtime
services. The [native CI workflow](../../.github/workflows/ci.yml) owns the actual
PHP/resolution matrix; report its exact candidate and results. These checks prove
installed provider composition, not production transport delivery, server
behavior, arbitrary callback recovery or release/publication authority.
