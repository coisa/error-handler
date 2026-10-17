---
name: coisa-error-handler
description: Integrate coisa/error-handler with configured private reporters, process callbacks, FastForward Interop service providers, and PSR-15 responses. Use when an application consumes this library; inspect its installed version before selecting an API.
license: MIT; see LICENSE
compatibility: Selected unreleased next-major source of coisa/error-handler; PHP ^8.3 and Composer 2. This is not the published 2.x API. Applications supply PSR-14/17 implementations and HTTP emission. No agent SDK or global installation is required.
metadata:
  author: Felipe Sayão Lobato Abreu
  api-baseline: "unreleased-next-major-source"
  source_url: https://github.com/coisa/error-handler
---

# CoiSA Error Handler

Configure reporting in an application and verify the actual delivery, callback
and public-response boundaries. Package: `coisa/error-handler`; namespace:
`CoiSA\ErrorHandler`. Retain the [source identity](PROVENANCE.md) when copying
this package. Skill installation, unrelated PHP work and library maintenance are
separate tasks.

## Inspect the consumer

Read applicable instructions, Composer files, the application bootstrap and its
existing reporter configuration. Inspect `composer show coisa/error-handler`,
the actual PHP runtime and available application implementations. This procedure
describes the selected unreleased next-major source with PHP `^8.3`; CI targets
PHP 8.3–8.5. Published 2.x installations need their matching 2.x skill instead.
A lockfile entry alone does not establish runtime availability. Inspect the
selected source and autoloader; do not install or upgrade dependencies solely
to fit these instructions. No published next-major Composer version is assumed.

Use the application's configuration and secret mechanisms. Error Handler accepts
objects; it does not load application configuration files or environment values
automatically. FastForward Container is a runtime dependency; the Interop service
provider is the container integration entrypoint. The old container and
`ConfigProvider` are removed in this source contract.

## Configure and verify ordinary reporting

Create `report.php` at the application root, beside `vendor/`. This complete
example uses an application-owned configuration array and a synthetic in-memory
reporter. Replace that reporter with the application's private sink after the
composition is verified.

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/vendor/autoload.php';

$reports = [];

$configuration = [
    'reporter' => new CallableThrowableHandler(
        /**
         * Retain the original synthetic failure without public output.
         *
         * @param Throwable $error the failure selected for private reporting
         */
        static function (Throwable $error) use (&$reports): void {
            $reports[] = $error;
        },
    ),
];

$errors = new ErrorHandler($configuration['reporter']);
$failure = new RuntimeException('Synthetic application failure');

$errors->handleThrowable($failure);

if ([$failure] !== $reports) {
    throw new RuntimeException('Expected one delivery of the original throwable.');
}

echo 'reported once; execution continued' . PHP_EOL;
```

Run `php report.php`; expect exit 0 and
`reported once; execution continued`. No native callback is installed. Direct
reporting returns when the reporter returns; the identical reporter failure
propagates. Reporting does not imply a process exit or HTTP response.

For configuration split across files, use the complete
[configured application](examples/README.md#configured-reporting):
`config/error-handler.php` supplies the reporter, the application factory applies
explicit overrides with `array_replace`, and `configured.php` loads the selected
consumer autoloader. Override keys win; missing or wrong-type reporters fail
before construction. These keys and the example factory belong to the example
application, not to a library configuration API.

```sh
php vendor/coisa/error-handler/resources/skills/coisa-error-handler/examples/configured.php ./vendor/autoload.php
```

When running a copied skill, use that copy's `examples/configured.php` and the
application's explicit autoloader path. Success prints
`configured reporting: original delivered once`. The example installs nothing
and does not write logs or contact a service.

## Choose the lifecycle and composition

| Application need | Supported route and observation |
| --- | --- |
| Explicit reporting | `ErrorHandler::handleThrowable()` as above; original delivered once, execution continues. |
| PHP warnings or uncaught exceptions | Register at the application bootstrap; follow [process scopes](references/process.md) and verify restoration. |
| FastForward integration | Supply reporters through the [Interop provider recipe](references/container.md); construction must install no callbacks. |
| HTTP | Supply PSR-17 implementations and select the [explicit public response recipe](references/http.md); verify private delivery and fixed body separately. |
| Logging, events, filtering or best-effort reporting | Select [reporter composition](references/reporters.md) only for that integration; supply the actual collaborators. |

FastForward caches services. The provider keeps process and middleware handlers
separate; invoke its reusable factory services for fresh independent scopes.
Do not mistake repeated `get()` calls for a transient lifetime.
Applications own interfaces' implementations, private diagnostic transports,
callback installation and HTTP emission. Registering a class does not imply
aggregate membership.

## Verify and report

Use the application's existing PHPUnit setup and synthetic fixtures. Check
successful delivery, missing/invalid configuration, override precedence and the
identical collaborator failure. For callbacks, check enabled/suppressed warnings
and restoration after success and failure. Native callbacks are process-wide;
run uncaught/fatal/termination experiments only in bounded child processes.

For HTTP, assert status, exact public body and one original private report.
Middleware reports before invoking a pure renderer. The provider defaults to a
generic body; keep output capture explicit and never inject the private reporter
into that renderer again. Do not infer successful delivery to an external logger from an
in-memory example.

Report installed package/PHP versions, chosen composition, changed files,
executed checks and concrete limits. Shipping files, copying the package,
discovery after a session refresh and successful agent use are separate results.
This procedure does not authorize deployment, publication, global installation
or production diagnostics.
