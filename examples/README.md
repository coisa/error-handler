# Executable major cookbook

These synthetic examples target the unreleased PHP 8.3+ branch. Use the exact
reviewed checkout/path package; installing the published 2.x package does not
select the major provider/lifecycle/HTTP contracts.

The source checkout supplies application implementations through development
dependencies. In a disposable application using that path package, add
`nyholm/psr7:^1.8` and `phly/phly-event-dispatcher:^1.0` for HTTP/event recipes.
Add optional `filp/whoops:^2.18.4` wherever the development HTML recipes are used.
FastForward Container and PSR interfaces resolve from the library's runtime graph.
No framework-specific container is required. Keep application locks/caches outside
the library and do not bypass actual runtime constraints.

Individual recipes use `bootstrap.php`, which selects `ERROR_HANDLER_AUTOLOAD`
or the checkout's autoloader and installs no callbacks. To run the bounded verifier
with an external application autoloader, install its development-only process tool:

```sh
composer require --dev 'symfony/process:^5.4 || ^6.4 || ^7.4 || ^8.0' --no-plugins --no-scripts --no-interaction
ERROR_HANDLER_AUTOLOAD=/absolute/application/vendor/autoload.php php examples/verify.php
```

Without that variable, run `php examples/verify.php` from the configured checkout.
It executes every recipe and documented mode with exact stdout/stderr and exit
expectations, including the deliberate CLI exit 1. See [verifier operation](verify.md).
The [cookbook](../docs/examples.md) contains all seventeen complete sources, including
imports and every executable branch, with only the leading license header omitted.
Its exact source parity and recipe inventory are checked alongside the
[site](../docs/index.html#examples). The four explicit logging/filtering/PHP-error/
best-effort adapters are preserved from delivered 2.x APIs; callable constructor
unions remain planned.

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
