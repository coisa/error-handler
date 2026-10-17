# Select and compose reporters

Read when selecting a built-in diagnostic adapter. Keep private reporting
independent from the public HTTP body. Every callable/dispatcher/logger below is
an application collaborator, not a discovered transport.

## Callable PHP errors and lifecycle

`CallableThrowableHandler($callback)` receives the original throwable and ignores
the callback's return value. `CallablePhpErrorHandler($callback)` receives
`(int $code, string $message, string $filename, int $line)` unchanged; return
values, including false/null, are ignored by its void contract. The callback
owns mask/conversion policy; this is not native false/fall-through.

All three ErrorHandler constructor inputs accept the matching interface or a
callable. The reporter is required; PHP-error and shutdown inputs default to null,
retaining their built-in policies. Normalization uses `CallableThrowableHandler`,
`CallablePhpErrorHandler` and `CallableShutdownHandler`; it invokes nothing during
construction. If an object implements the relevant interface and `__invoke()`, the interface method
and original instance win. Invalid non-callable values raise TypeError; callback
signature errors arise when PHP invokes the callback, not through preflight calls.

The callable shapes are `callable(Throwable): mixed`,
`callable(int, string, string, int): mixed` and `callable(): mixed` respectively.
`CallableShutdownHandler($callback)` or a direct third-argument callback is an
explicit shutdown policy, not fatal recovery. The ErrorHandler lifecycle still
requires an active registration before shutdown delivery. Adapter failures retain
the handler's reporting/propagation policy. Keep explicit adapters when another
API requires an interface, such as an aggregate or filtering delegate.

For FastForward, bind a callback value through `ServiceFactory($callback)` under
the matching interface service ID and merge required application overrides last.
The explicit handler factory preserves cached process services and fresh scope
creation; arbitrary union autowiring is not a prerequisite.

## Filtering and ordered delivery

Using the configured reporter from the ordinary setup:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\FilteringThrowableHandler;

$selected = new FilteringThrowableHandler(
    $configuration['reporter'],
    /**
     * Route PHP warnings by severity, without interpreting exception codes.
     *
     * @param Throwable $failure the original diagnostic event
     *
     * @return bool whether this reporter should receive it
     */
    static function (Throwable $failure): bool {
        return $failure instanceof ErrorException
            && E_USER_WARNING === $failure->getSeverity();
    },
);

$errors = new ErrorHandler($selected);
```

True forwards once; false skips only that delegate. Nonboolean predicate results
raise TypeError; predicate/reporter failures propagate without retry. Filtering
does not change PHP suppression, callback lifecycle or HTTP rendering.

`ThrowableHandlerAggregate` is ordered and stops at the first reporter failure.
`BestEffortThrowableHandlerAggregate(...$reporters)` attempts each fixed slot once
with the original, including duplicate slots. If any fail, it throws one
`AggregateReportingException` after all attempts, even on partial success.
`getOriginalThrowable()` retains the original; `getFailures()` is ordered, and
the previous cause is the original. An empty/all-success pass returns normally.
Construct these explicitly; neither supplies retries, a recursion guard or
durable delivery.

## PSR-3

With the application's actual `Psr\Log\LoggerInterface $logger`:

```php
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use Psr\Log\LogLevel;

$reporter = new LogThrowableHandler(
    $logger,
    LogLevel::ERROR,
    ['component' => 'application'],
);

$errors = new ErrorHandler($reporter);
$errors->handleThrowable(new RuntimeException('Synthetic private failure'));
```

The adapter logs the exact message once (including an empty message) and places
the original under context key `exception`, overriding caller data for that key
without coercing other values. Level validation belongs to the logger; its
identical failure propagates. Providing a logger alone does not select this
adapter or attach it to the default aggregate.

## PSR-14

Supply the application's real `Psr\EventDispatcher\EventDispatcherInterface` to
`DispatchThrowableHandler` for the original throwable, or to
`DispatchErrorEventThrowableHandler` for an `ErrorEvent`.
`CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider`
supplies its configured listeners for throwables;
`CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider`
selects listeners for ErrorEvent objects. Both constructors accept variadic
callable listeners.
`LogErrorEventListener` logs ErrorEvent data through the supplied PSR-3 logger.
Configuring both raw and wrapped adapters causes separate deliveries; choose that
deliberately. Dispatcher/listener failures propagate and are not retried.

Use the consumer's existing PHPUnit doubles or synthetic recording collaborators.
Verify original-object identity, order/nonmatch behavior, empty aggregates,
partial reporting failures and exact logger context. No example proves delivery
to a real external transport.
