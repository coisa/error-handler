# Executable cookbook

These synthetic examples exercise the library's reporting, lifecycle, provider
and HTTP contracts on PHP `^8.3`. Use examples matching the installed API; follow
the [migration guide](../docs/migration.md) when upgrading from 2.x.

The top-level `examples/` directory is source-checkout material and is excluded
from Composer distribution archives. For an installed library, start with the
[complete bundled application programs](../resources/skills/coisa-error-handler/examples/README.md)
that ship under `vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/`.
They accept the application's explicit autoloader and do not need the checkout's
development autoload map.

To use the cookbook below, select a matching inspected source checkout and keep
its complete `examples/` directory, including
[`bootstrap.php`](https://github.com/coisa/error-handler/blob/master/examples/bootstrap.php)
and [`check.php`](https://github.com/coisa/error-handler/blob/master/examples/check.php).
The bootstrap selects the Composer autoloader and loads the assertion helper;
copying only an individual recipe is insufficient. From an existing application,
run a checkout recipe against that application's graph:

```sh
ERROR_HANDLER_AUTOLOAD=/absolute/application/vendor/autoload.php php /absolute/source-checkout/examples/reporters.php
```

Expect exit 0, empty stderr and exactly:

```text
first: Synthetic failure
second: Synthetic failure
```

Use the same autoloader selection for the other individual recipes. When using
the checkout's own graph, follow its
[dependency setup](https://github.com/coisa/error-handler/blob/master/CONTRIBUTING.md)
and run commands from that checkout root.

The source checkout supplies application implementations through development
dependencies. In a disposable application, add
`nyholm/psr7:^1.8.2` and `phly/phly-event-dispatcher:^1.6` for HTTP/event recipes.
Add optional `filp/whoops:^2.18.4` wherever the development HTML recipes are used.
FastForward Container and PSR interfaces resolve from the library's runtime graph.
No framework-specific container is required. Keep application locks/caches outside
the library and do not bypass actual runtime constraints.

Individual recipes use `bootstrap.php`, which selects `ERROR_HANDLER_AUTOLOAD`
or the checkout's autoloader and installs no callbacks. To run the bounded verifier
with an external application autoloader, install its development-only process tool:

```sh
composer require --dev 'symfony/process:^7.4 || ^8.1' --no-plugins --no-scripts --no-interaction
ERROR_HANDLER_AUTOLOAD=/absolute/application/vendor/autoload.php php examples/verify.php
```

Without that variable, run `php examples/verify.php` from the configured checkout.
It executes every recipe and documented mode with exact stdout/stderr and exit
expectations, including the deliberate CLI exit 1. See [verifier operation](verify.md).
The [cookbook](../docs/examples.md) contains all seventeen complete sources, including
imports and every executable branch, with only the leading license header omitted.
Its exact source parity and recipe inventory are checked alongside the
[site](../docs/index.html#examples). The four explicit logging/filtering/PHP-error/
best-effort adapters are preserved from delivered 2.x APIs. The PHP-error recipe
passes three callbacks directly to ErrorHandler while retaining explicit mask
policy and active-scope shutdown ownership.

The recipes do not start a server, emit HTTP to clients or deliver real logs.
Applications own transports, private diagnostics and emission.
## JSON and development HTML

```sh
php examples/http-json.php public
php examples/http-json.php debug
php examples/http-whoops.php public
php examples/http-whoops.php development
php examples/provider-json.php public
php examples/provider-json.php debug
php examples/provider-whoops.php default
php examples/provider-whoops.php configured
```

The two manual recipes pass a pure renderer directly to middleware. The provider
recipes compose independent base, selected profile and application maps in one
aggregate for a required private reporter. No renderer reports the original again.
JSON accepts an explicit debug bool; Whoops page customization is an application
service override. A cached configured page is reused sequentially, with fresh
Whoops runs, HTTP bodies and middleware scopes. No recipe emits a response or
enables diagnostics from client input. See [HTTP boundaries](../docs/http.md).

Explicit public renderers must balance their own output buffers. If a renderer
closes capture and returns, the stream factory uses the generic body while
leaving caller bytes, depth and output callbacks alone. It cannot undo the
renderer's own writes, flushes or buffer changes. PHPUnit verifies this boundary
with native buffers; no additional recipe runner is required.
