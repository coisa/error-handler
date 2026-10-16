# Executable major cookbook

These synthetic examples target the unreleased PHP 8.3+ branch. Use the exact
reviewed checkout/path package; installing the published 2.x package does not
select the major provider/lifecycle/HTTP contracts.

The source checkout supplies application implementations through development
dependencies. In a disposable application using that path package, add
`nyholm/psr7:^1.8` and `phly/phly-event-dispatcher:^1.0` for HTTP/event recipes.
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
It executes all twelve recipes with exact stdout/stderr and exit expectations,
including the deliberate CLI exit 1. See [verifier operation](verify.md).
The [cookbook](../docs/examples.md) and [site](../docs/index.html#examples) display
the same sources. The three explicit logging/filtering/PHP-error adapters are
preserved from delivered 2.x APIs; callable constructor unions remain planned.

The recipes do not start a server, emit HTTP to clients or deliver real logs.
Applications own transports, private diagnostics and emission.
