# Process reporting and callbacks

Read when installing PHP callbacks or deciding where a caught failure is reported.
Direct `handleThrowable()` needs no registration and returns normally after a
successful reporter call.

For PHP warnings in a bounded application scope, use a distinct configured
`ErrorHandler`:

```php
$errors->register();

try {
    trigger_error('Synthetic warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}
```

Verify one report for an enabled warning and no report under the application's
suppression policy. The default `ThrowErrorExceptionPhpErrorHandler` checks
`error_reporting()`; a custom adapter owns its own conversion/mask policy.
The original reporter failure propagates. During registration, recursion or
reporter failure stops further retries. Independent unregistered direct
deliveries remain usable; this does not recover an exiting/fatal reporter.

To report an exception caught by application code:

```php
$errors->register();

try {
    performApplicationWork();
} catch (Throwable $failure) {
    $errors->handleThrowable($failure);
} finally {
    $errors->unregister();
}
```

Here `performApplicationWork()` represents the application's existing operation.
Choose rethrow/exit policy explicitly; rethrowing into another installed reporter
can cause a second delivery. For an uncaught process exception, register once at
bootstrap and let the exception reach that installed callback. Unregistering in an
unwinding `finally` happens before the uncaught-exception callback, so it may
remove the intended reporter first. The registered uncaught-exception callback exits 1 after a successful report.
Direct `handleThrowable()` calls return and leave exit policy to their caller.

Callbacks are process-wide, including under fibers. Restore nested scopes in
reverse order with distinct handlers. Reusing one handler for nested registration
does not create an independent scope. Out-of-order removal raises `LogicException`; do not overwrite another owner.
PHP cannot remove queued shutdown functions. This core queues its shutdown
callback at most once per instance and only an active scope reports. Creating
unbounded new handlers still grows that queue; registration is not concurrent
fiber/request isolation.
Fatal shutdown reporting observes a failure and does not recover execution.

Check callback identity/restoration after success and reporter failure using the
application's existing PHPUnit setup. Run uncaught exceptions, fatal errors and
terminating callback experiments only in bounded child processes. Library source
checkouts provide the documented PHPUnit Process suite; copied consumers should
keep their own small fixture and normal test runner. Do not use production logs,
real credentials or native-function overrides as fixtures.
