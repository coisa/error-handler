---
name: coisa-error-handler
description: Integrate coisa/error-handler with configured private reporters, process callbacks, legacy PSR-11 or ConfigProvider composition, PSR-15 responses and available JSON/Whoops/Interop profiles. Use when an application consumes this library; inspect its installed version before selecting an API.
license: MIT; see LICENSE
compatibility: coisa/error-handler ^2.4 for common reporting, starting with 2.4.0; PHP ^7.4 || ^8.0 and Composer 2. Advanced JSON/Whoops/Interop routes require ^2.5, first published in 2.5.0, and their named classes in the installed source. Whoops requires optional filp/whoops ^2.18.4; optional FastForward integration requires fast-forward/container ^1.9 and application PHP 8.3+. Applications supply PSR-14/17 implementations and HTTP emission. No agent SDK or global installation is required.
metadata:
  author: Felipe Sayão Lobato Abreu
  api-baseline: "2.4.0"
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
supports `^2.4`; earlier versions are outside its supported API contract. A lockfile
entry alone does not establish runtime availability. Do not install or upgrade
dependencies solely to fit this skill.

The common 2.4 baseline does not establish availability of every additive
JSON/Whoops/provider class. Those routes were published in 2.5.0 and require
`coisa/error-handler:^2.5`. Before selecting them, inspect the installed version,
matching source and named symbols using the
[advanced availability checks](references/json.md#inspect-the-selected-installation).
If they are absent, use the ordinary route or existing application response
implementation; do not substitute another API or upgrade automatically.
For development HTML, also follow the focused
[Whoops availability and diagnostic checks](references/whoops.md#inspect-the-selected-installation).

For an authorized new application installation:

```sh
composer require 'coisa/error-handler:^2.4' --no-plugins --no-scripts
```

Use the application's configuration and secret mechanisms. Error Handler accepts
objects; it does not load application configuration files or environment values
automatically. `ConfigProvider` is a separate legacy container integration.

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
| Existing PSR-11 or Laminas application | Supply its reporters to the [legacy container recipe](references/container.md); construction must install no callbacks. |
| HTTP | Supply PSR-17 implementations and select the [explicit public response recipe](references/http.md); verify private delivery and fixed body separately. |
| JSON or Interop/FastForward profiles from 2.5.0 | Require `^2.5`, verify the installed symbols, then use [explicit JSON composition](references/json.md); applications select independent providers and trusted debug configuration. |
| Explicit development HTML from 2.5.0 | Require `^2.5`, verify the installed APIs and optional Whoops, then use [HTML composition](references/whoops.md); the application selects diagnostic exposure and one aggregate for the Whoops profile. |
| Logging, events, filtering or best-effort reporting | Select [reporter composition](references/reporters.md) only for that integration; supply the actual collaborators. |

The built-in container caches handlers. A fresh wrapper does not establish a
fresh handler; construct distinct handlers for independent nested scopes.
Applications own interfaces' implementations, private diagnostic transports,
callback installation and HTTP emission. Registering a class does not imply
aggregate membership.

## Verify and report

Use the application's existing PHPUnit setup and synthetic fixtures. Check
successful delivery, missing/invalid configuration, override precedence and the
identical collaborator failure. For callbacks, check enabled/suppressed warnings
and restoration after success and failure. Native callbacks are process-wide;
run uncaught/fatal/termination experiments only in bounded child processes.

For HTTP, assert status, exact public body and original private report. The
legacy captured-output default can expose exception details; silence is not
redaction. Do not infer successful delivery to an external logger from an
in-memory example.

Report installed package/PHP versions, chosen composition, changed files,
executed checks and concrete limits. Shipping files, copying the package,
discovery after a session refresh and successful agent use are separate results.
This procedure does not authorize deployment, publication, global installation
or production diagnostics.

For suspected security issues, follow the source repository's
[security policy](https://github.com/coisa/error-handler/security/policy) and use
its private reporting channel. Use ordinary issues for non-security bugs and features.
