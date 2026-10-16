---
name: coisa-error-handler
description: Integrate the existing coisa/error-handler 2.x PHP API with explicit reporters, process callbacks, legacy PSR-11/ConfigProvider composition or PSR-15 middleware, then verify reporting, output and callback restoration.
license: MIT
compatibility: PHP and Composer matching the selected 2.x package; PHP 7.4-compatible application code. Applications supply PSR-14/17 implementations and HTTP emission.
metadata:
  source_url: https://github.com/coisa/error-handler
---

# CoiSA Error Handler 2.x integration

Package: coisa/error-handler; namespace: CoiSA\ErrorHandler.
[Source and maintenance](https://github.com/coisa/error-handler).
Retain that identity when copying the package into a consumer. This procedure
uses the existing 2.x API, not the proposed FastForward-based major.

## Select the API and scope

Read the consumer's composer.json/lock, composer show coisa/error-handler, PHP
version and installed interfaces. The declared runtime is ^7.4 || ^8.0;
development integrations may be narrower. A pending Changeset is not a release.
For clean PHP 7.4 container use, verify the selected release/check-out contains
the [PSR-11 metadata](https://github.com/coisa/error-handler/issues/13) and
[alias compatibility](https://github.com/coisa/error-handler/issues/40) repairs.
Do not raise PHP requirements or replace the container to make a recipe work.

Use direct handleThrowable() for explicit reporting without callbacks. Use one
registered handler at process bootstrap for uncaught exceptions. For a caught
operation, report inside its catch and unregister in finally. Unregistering in an
unwinding finally happens before the uncaught-exception callback and can prevent
that handler from seeing the exception.

## Complete ordinary setup

Install with Composer 2:

```sh
composer require 'coisa/error-handler:^2.0' --no-plugins --no-scripts
```

In an application entrypoint, load its autoloader and choose its private reporter:

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/vendor/autoload.php';

$reports = 0;
$errors = new ErrorHandler(new CallableThrowableHandler(
    /**
     * Record a synthetic delivery before choosing an application transport.
     *
     * @param Throwable $error the reported PHP warning
     */
    static function (Throwable $error) use (&$reports): void {
        ++$reports; // Replace this synthetic counter with the application's private sink.
    },
));

$errors->register();

try {
    trigger_error('Synthetic warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}

if (1 !== $reports) {
    throw new RuntimeException('The configured reporter did not receive the warning.');
}
```

Run the entrypoint with PHP; expect exit 0 and one synthetic report. The default
PHP-error adapter honors error_reporting/suppression. Direct reporting returns
when its reporter returns; reporter failures propagate. ThrowableHandlerAggregate
is ordered and stops when a reporter throws. A registered uncaught report does
not automatically exit 1. Fatal shutdown reporting observes a native failure;
it does not recover execution.

## Conditional composition

For an explicit PHP-error callback, first verify the selected source contains
Handler\CallablePhpErrorHandler. Wrap the callable and inject that interface
instance as ErrorHandler's second argument. It receives four unchanged arguments
(code, message, filename, line), once. Return values, including false/null, are
ignored by the void contract; this is not native false/fall-through. The callback
owns mask/conversion policy and its failure propagates. It installs no callbacks
or default factory. Keep the 2.x constructor typed; bare-callable constructor inputs
are major-only. Follow the [PHP-error recipe](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php)
and verify explicit suppression policy before replacing the default adapter.

For selected diagnostic delivery, explicitly construct
Handler\FilteringThrowableHandler($reporter, $predicate). The required predicate
accepts the original Throwable and returns bool: true forwards once, false skips
only that delegate. Non-boolean results raise TypeError; predicate/reporter
failures propagate without retry. Use getSeverity() on ErrorException for PHP
severity, not arbitrary exception codes. It changes no PHP suppression, callback
or HTTP response policy. No container/default aggregate discovers it. Verify
that the selected source version contains this additive API, then follow the
[filtering recipe](https://github.com/coisa/error-handler/blob/master/examples/filtering.php).

For best-effort delivery, verify the selected source contains
Handler\BestEffortThrowableHandlerAggregate and Exception\AggregateReportingException.
Construct the aggregate explicitly with a fixed reporter list. Each invocation
attempts every slot once, in order, with the same original; duplicates are separate
attempts. Any failures produce one aggregate exception after all attempts, even
with partial success. Catch it to retain getOriginalThrowable() and the ordered
getFailures(); its previous cause is the original. Zero/all-success passes return.
The existing fail-fast aggregate/defaults remain unchanged. No factory, retry,
reentry guard, durable delivery or HTTP redaction is supplied. Use the
[synthetic recipe](https://github.com/coisa/error-handler/blob/master/examples/best-effort.php)
and [terminal policy](https://github.com/coisa/error-handler/blob/master/docs/api.md#best-effort-reporting);
the original and reporter failures remain private diagnostics.

For the existing container, construct
CoiSA\ErrorHandler\Container\ErrorHandlerContainer($application) with an optional
application PSR-11 parent, then resolve ErrorHandler::class. Provide a reporter:
the default aggregate is empty and silent. ConfigProvider exposes the existing
aliases/factories and Laminas discovery metadata. Factories are under
Container\Factory; discovery/construction does not register callbacks.
Services are cached. For distinct nested handlers, invoke ErrorHandlerFactory
explicitly or construct the core directly; a fresh middleware wrapper can still
reuse a cached handler. Read the matching source's
[container guide](https://github.com/coisa/error-handler/blob/master/docs/container.md)
when selecting this route; it includes the complete application recipe.

For PSR-14 or PSR-3, supply an application dispatcher/logger. Raw throwable and
ErrorEvent adapters are separate deliveries. Read the matching
[API guide](https://github.com/coisa/error-handler/blob/master/docs/api.md)
for providers/listeners, aggregate ordering and the logging adapter's string
message behavior.

For direct PSR-3 reporting, first verify the selected checkout/release contains
Handler\LogThrowableHandler; it is an opt-in 2.x adapter, not an API promised
by every ^2.0 installation. Construct it explicitly with the application logger,
optional level (ERROR by default) and context. It logs the exact message once,
including empty, and keeps the original throwable under exception over caller
context without coercing other values. Level validity belongs to the logger;
the identical logger failure propagates. No factory/discovery is added. Prefer
an explicit process ErrorHandler; a silent or filtered reporter bound globally
as the legacy ThrowableHandlerInterface renderer can fall back to public diagnostic text.
See the [direct logging contract](https://github.com/coisa/error-handler/blob/master/docs/api.md#direct-psr-3-logging)
and [runnable recipe](https://github.com/coisa/error-handler/blob/master/examples/logging.php).

For HTTP, supply PSR-17 response/stream factories and an application emitter.
Middleware registers/restores its lifecycle handler and delegates caught
throwables to the response factory. It does not report independently first.
The default stream factory invokes the reporter while capturing output; empty
or "0" output falls back to exception text. This can expose diagnostics. Use
an explicit application response factory that reports privately once and creates
a fixed public response. Read the matching
[HTTP recipe](https://github.com/coisa/error-handler/blob/master/docs/http.md)
before wiring this route; do not invent a built-in pure/default JSON renderer.

For a source/release containing the [exceptional capture cleanup repair](https://github.com/coisa/error-handler/issues/24),
reporter/capture failure triggers best-effort removal of removable buffers above
the entry level. Cleanup stops at a protected buffer, failed close or absent progress;
earlier caller buffers are not deliberately closed. A throwing output callback does
not stop cleanup when PHP has removed its buffer and cleanup can still make progress.
If cleanup returns, the same primary failure propagates. An output handler that
calls exit()/die() or triggers an engine fatal can prevent that propagation and
terminate execution. Callback side effects cannot be undone. Verify the selected
source/release before relying on this repair; successful empty/"0" fallback and
unbalanced nested capture retain their legacy behavior.

## Verify and report

Use distinct objects for nested scopes and restore callbacks in reverse order.
Native callbacks are process-wide, including under fibers. PHP cannot remove
queued shutdown functions; repeated register cycles are not a bounded worker
scope mechanism.

Verify direct delivery, enabled/suppressed warnings, callback restoration after
success/failure and configured response bodies. Run uncaught/fatal experiments
only through a bounded child fixture. Read the
[complete 2.x cookbook](https://github.com/coisa/error-handler/blob/master/docs/examples.md)
with its [application setup and verifier](https://github.com/coisa/error-handler/blob/master/examples/README.md).
In a consumer, retain focused tests against its own reporters and transports.
Library/example checks do not prove delivery to a real logging service.

When using capture, verify caller-buffer bytes/levels and primary failure identity
with the application's reporter. Exercise protected, throwing or terminating output
handlers only in bounded children. In a source checkout with development dependencies,
`vendor/bin/phpunit --testsuite Unit` exercises capture directly and
`vendor/bin/phpunit --testsuite Process` checks genuine hazardous native operations
through bounded Symfony Process children. These checks do not certify arbitrary
callback side effects or process termination recovery; external child execution
does not contribute to the parent's unit-coverage measurement.

Return observed package/PHP versions, integration changes, executed checks and
remaining limits. This procedure does not deploy, publish, install globally or
operate production diagnostics.
