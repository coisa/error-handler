# Complete 2.x cookbook

These complete recipes use the `coisa/error-handler` 2.x API and PHP 7.4-compatible syntax. They match the executable sources in this checkout. The common baseline is `^2.4`; JSON/Whoops adapters and Interop providers were published in 2.5.0 and need `^2.5`. Verify the selected installation. The optional Interop/FastForward recipes require PHP 8.3+ at runtime. They do not adopt the proposed major's required container, constructor callables or report-before-render middleware.

Start with the [application dependencies and execution setup](https://github.com/coisa/error-handler/blob/master/examples/README.md). The recipes share [bootstrap.php](https://github.com/coisa/error-handler/blob/master/examples/bootstrap.php) for the application's Composer autoloader and [check.php](https://github.com/coisa/error-handler/blob/master/examples/check.php) for their synthetic assertions. When adapting a recipe to an application, use its existing autoloader and tests.

Run all modes from the source checkout with the [bounded verifier](https://github.com/coisa/error-handler/blob/master/examples/verify.md):

```sh
php examples/verify.php
```

Set `ERROR_HANDLER_AUTOLOAD` as described in the setup guide when using a disposable application's dependencies. Process/fatal/HTTP scenarios belong in that bounded execution. The library never starts a server, chooses a logging transport or emits a response to a client.

The displayed PHP retains imports, declarations, spacing and every executable branch. Only each source file's license header is omitted here; the [MIT license](https://github.com/coisa/error-handler/blob/master/LICENSE) and full headers remain in the repository. The existing documentation validator checks these complete recipes against source in addition to the website excerpts.

| Recipe | Main responsibility |
| --- | --- |
| [direct.php](#direct-throwable-reporting) | Direct throwable reporting |
| [logging.php](#direct-psr-3-logging) | Direct PSR-3 logging |
| [process.php](#process-callbacks-and-scoped-restoration) | Process callbacks and scoped restoration |
| [handlers.php](#callable-adapters-aggregation-and-php-error-conversion) | Callable adapters, aggregation and PHP error conversion |
| [php-errors.php](#explicit-php-error-callback-policy) | Explicit PHP-error callback policy |
| [filtering.php](#conditional-diagnostic-reporting) | Conditional diagnostic reporting |
| [best-effort.php](#attempt-every-reporter-and-retain-all-failures) | Attempt every reporter and retain all failures |
| [events.php](#psr-14-dispatch-listeners-and-logging) | PSR-14 dispatch, listeners and logging |
| [container.php](#existing-container-and-configprovider-integration) | Existing container and ConfigProvider integration |
| [http.php](#http-responses-output-capture-and-a-safe-application-adapter) | HTTP responses, output capture and a safe application adapter |
| [http-json.php](#pure-json-with-one-private-report) | Pure JSON with one private report |
| [provider-json.php](#optional-interop-json-profile) | Optional Interop JSON profile, PHP 8.3+ |
| [http-whoops.php](#optional-development-html-with-whoops) | Explicit development HTML and private reporting |
| [provider-whoops.php](#optional-interop-whoops-profile) | Optional Interop Whoops profile, PHP 8.3+ |

## Choose a recipe and its prerequisites

Start with direct reporting, then add the composition your application needs. The adapters can be combined: a filter can wrap a logger, an aggregate can contain multiple reporters, and the core can use that aggregate. Decide whether one reporter failure should stop later deliveries before choosing the fail-fast or best-effort aggregate.

| Recipes | Additional application implementation | What success establishes |
| --- | --- | --- |
| Direct, handlers, PHP errors, filtering and best-effort | None beyond the library and Composer autoloader; their reporters use synthetic callbacks | The selected delivery, ordering, conversion or failure contract in memory. |
| Direct logging | A PSR-3 logger; the recipe provides a recording implementation | Exact message, selected level and original throwable in context, without a real log transport. |
| Process callbacks | No additional implementation for the built-in defaults | Native callback behavior and restoration in bounded child processes; fatal observation does not establish recovery. |
| Events | A PSR-14 dispatcher; the recipe uses Phly | Raw and wrapped dispatch, listener selection and recording-log delivery. |
| Container | A parent PSR-11 implementation; the recipe uses Laminas ServiceManager | Overrides, the three aliases/seven factories and cached versus explicitly constructed core identity. Direct core construction needs no container. |
| HTTP | PSR-17 stream/response factories; the recipe uses Nyholm | Returned response tuples, private reporting and callback restoration. No HTTP response is emitted. |

The library requires `psr/container`, `psr/event-dispatcher`, `psr/http-factory`, `psr/http-server-middleware` and `psr/log` at runtime; Composer also resolves their interface dependencies, including PSR-7 message interfaces and PSR-15 request-handler contracts. These packages define interoperability contracts. Nyholm, Phly and Laminas are implementations used by particular recipes, not mandatory integrations for every application. The installed application's lockfile records the actual dependency versions.

From the source checkout, run `php examples/direct.php` first. Expected output is:

```text
reported: Example failure
continued
```

Run `php examples/verify.php` after completing setup. It prints each passed scenario and ends with the scenario total and executed PHP version. Add `--with-fast-forward` on PHP 8.3+ with the documented FastForward dependency to include the optional provider scenarios. A passing recording example verifies the composition shown; configure and test the application's real transport and emitter separately. If setup fails, check the selected autoloader, actual PHP version and recipe-specific implementations before changing the library's requirements.

## Direct throwable reporting

Construct the handler explicitly and report without registering native callbacks. The call returns normally so the application can continue.

Source: [examples/direct.php](https://github.com/coisa/error-handler/blob/master/examples/direct.php).

<!-- source: examples/direct.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$errors = new ErrorHandler(new CallableThrowableHandler(
    /**
     * Print only the synthetic message used by this executable example.
     *
     * @param Throwable $error the synthetic failure to report
     */
    static function (Throwable $error): void {
        echo 'reported: ' . $error->getMessage() . PHP_EOL;
    },
));

$errors->handleThrowable(new RuntimeException('Example failure'));

echo 'continued' . PHP_EOL;
```

## Direct PSR-3 logging

Inject an application logger, choose a level and preserve the original throwable in context. The recording logger demonstrates exact and empty messages without a real transport.

Source: [examples/logging.php](https://github.com/coisa/error-handler/blob/master/examples/logging.php).

<!-- source: examples/logging.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

require __DIR__ . '/bootstrap.php';

$logger = new class extends AbstractLogger {
    /** @var list<array{level: mixed, message: mixed, context: array<array-key, mixed>}> Synthetic log records. */
    public array $records = [];

    /**
     * Record arguments without transport or output, using signatures compatible with PSR-3 1.x–3.x.
     *
     * @param mixed                   $level   the level chosen by the application
     * @param object|string           $message the logger's message argument
     * @param array<array-key, mixed> $context structured context retained without coercion
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
};

$context = ['component' => 'worker', 'exception' => new LogicException('Caller context')];
$failure = new RuntimeException('Synthetic worker failure');
$reporter = new LogThrowableHandler($logger, LogLevel::CRITICAL, $context);

$process = new ErrorHandler($reporter);

$process->handleThrowable($failure);

$empty = new RuntimeException('');
$process->handleThrowable($empty);

exampleCheck(2 === count($logger->records), 'Each adapter invocation must make one logger call.');
exampleCheck(LogLevel::CRITICAL === $logger->records[0]['level'], 'The configured level changed.');
exampleCheck($failure->getMessage() === $logger->records[0]['message'], 'The exact message changed.');
exampleCheck($failure === $logger->records[0]['context']['exception'], 'The original throwable must win its key.');
exampleCheck('worker' === $logger->records[0]['context']['component'], 'Caller context changed.');
exampleCheck($context['exception'] instanceof LogicException, 'Caller context was mutated.');

exampleCheck('' === $logger->records[1]['message'], 'An empty message must stay empty.');
exampleCheck($empty === $logger->records[1]['context']['exception'], 'The second original throwable changed.');

echo 'direct PSR-3 logging, exact messages and original context passed' . PHP_EOL;
```

## Process callbacks and scoped restoration

The existing modes cover warnings, scoped callback restoration, uncaught exceptions and a custom shutdown callback. Run these and the verifier-only fatal mode through the bounded verifier; the current 2.x uncaught path does not promise a nonzero exit.

Source: [examples/process.php](https://github.com/coisa/error-handler/blob/master/examples/process.php).

<!-- source: examples/process.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'warning';
$reporter = new CallableThrowableHandler(
    /**
     * Record only synthetic process-fixture messages.
     *
     * @param Throwable $error the warning, uncaught failure or native fatal fixture
     */
    static function (Throwable $error): void {
        echo 'reported: ' . $error->getMessage() . PHP_EOL;
    },
);
$shutdown = 'shutdown' === $mode
    ? new CallableShutdownHandler(
        /** Demonstrate an application-owned shutdown callback. */
        static function (): void {
            echo 'shutdown callback' . PHP_EOL;
        },
    )
    : null;
$errors = new ErrorHandler($reporter, null, $shutdown);

if ('scope' === $mode) {
    /** Represent an application's already installed PHP error callback. */
    $previousError = static fn(): bool => true;

    /**
     * Represent an application's already installed exception callback.
     *
     * @param Throwable $error an unexpected failure escaping the fixture
     *
     * @throws LogicException if the surrounding callback is unexpectedly invoked
     */
    $previousException = static function (Throwable $error): void {
        throw new LogicException('The surrounding exception callback must remain unused.', 0, $error);
    };

    set_error_handler($previousError);
    set_exception_handler($previousException);

    $errors->register();

    try {
        trigger_error('Scoped warning', E_USER_WARNING);
    } finally {
        $errors->unregister();
    }

    $actualError = set_error_handler($previousError);
    restore_error_handler();
    $actualException = set_exception_handler($previousException);
    restore_exception_handler();

    restore_error_handler();
    restore_exception_handler();

    exampleCheck(
        $actualError === $previousError && $actualException === $previousException,
        'Callbacks were not restored.',
    );

    echo 'callbacks restored' . PHP_EOL;

    return;
}

$errors->register();

if ('uncaught' === $mode) {
    throw new RuntimeException('Uncaught fixture');
}

if ('fatal' === $mode) {
    exampleCheck(
        '1' === getenv('ERROR_HANDLER_BOUNDED_FIXTURE'),
        'Run the fatal fixture only through examples/verify.php.',
    );

    eval('function exampleFatalDuplicate() {} function exampleFatalDuplicate() {}');
}

if ('warning' === $mode) {
    trigger_error('Process warning', E_USER_WARNING);

    echo 'continued' . PHP_EOL;

    return;
}

exampleCheck('shutdown' === $mode, 'Unknown process example mode.');

echo 'leaving main' . PHP_EOL;
```

## Callable adapters, aggregation and PHP error conversion

Compose callable throwable/shutdown adapters and the fail-fast aggregate. Exercise attach order, original-object delivery, conversion, reporting-mask suppression and the shutdown adapter's nonfatal path.

Source: [examples/handlers.php](https://github.com/coisa/error-handler/blob/master/examples/handlers.php).

<!-- source: examples/handlers.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;

require __DIR__ . '/bootstrap.php';

$calls = [];
$first = new CallableThrowableHandler(
    /**
     * Record the first configured delivery.
     *
     * @param Throwable $error the synthetic aggregate input
     */
    static function (Throwable $error) use (&$calls): void {
        $calls[] = 'first';
    },
);
$second = new CallableThrowableHandler(
    /**
     * Record an attached delivery after the constructor's reporters.
     *
     * @param Throwable $error the synthetic aggregate input
     */
    static function (Throwable $error) use (&$calls): void {
        $calls[] = 'second';
    },
);
$aggregate = new ThrowableHandlerAggregate($first);
$aggregate->attach($second);
$aggregate->handleThrowable(new RuntimeException('Aggregate fixture'));

exampleCheck(['first', 'second'] === $calls, 'Aggregate ordering changed.');

$failure = new RuntimeException('Reporter failure');
$stopped = false;
$failFast = new ThrowableHandlerAggregate(
    new CallableThrowableHandler(
        /**
         * Fail the first reporter to demonstrate propagation.
         *
         * @param Throwable $error the synthetic aggregate input
         *
         * @throws RuntimeException the exact configured reporter failure
         */
        static function (Throwable $error) use ($failure): void {
            throw $failure;
        },
    ),
    new CallableThrowableHandler(
        /**
         * Record an unexpected delivery after the first reporter fails.
         *
         * @param Throwable $error the synthetic aggregate input
         */
        static function (Throwable $error) use (&$stopped): void {
            $stopped = true;
        },
    ),
);

try {
    $failFast->handleThrowable($failure);

    throw new LogicException('Reporter failure was swallowed.');
} catch (RuntimeException $error) {
    exampleCheck($error === $failure && !$stopped, 'Aggregate failure behavior changed.');
}

$phpErrors = new ThrowErrorExceptionPhpErrorHandler();
$level = error_reporting(E_ALL);

try {
    try {
        $phpErrors->handlePhpError(E_USER_WARNING, 'Converted warning', __FILE__, __LINE__);

        throw new LogicException('An enabled warning was not converted.');
    } catch (ErrorException $error) {
        exampleCheck(E_USER_WARNING === $error->getSeverity(), 'PHP severity was lost.');
    }

    error_reporting(0);
    $phpErrors->handlePhpError(E_USER_WARNING, 'Suppressed warning', __FILE__, __LINE__);
} finally {
    error_reporting($level);
}

$shutdownCalls = 0;
$shutdown = new CallableShutdownHandler(
    /** Demonstrate direct invocation of a custom shutdown adapter. */
    static function () use (&$shutdownCalls): void {
        ++$shutdownCalls;
    },
);
$shutdown->handleShutdown();

exampleCheck(1 === $shutdownCalls, 'Custom shutdown callback was not invoked.');

// No fatal error exists in this fresh process, so the default adapter is a no-op.
(new PhpLastErrorShutdownHandler($phpErrors))->handleShutdown();

echo 'callable, aggregate, conversion, suppression and shutdown adapters passed' . PHP_EOL;
```

## Explicit PHP-error callback policy

Supply the four-argument CallablePhpErrorHandler explicitly. The callback owns mask/conversion policy; its return value is ignored by the void interface, and failures propagate.

Source: [examples/php-errors.php](https://github.com/coisa/error-handler/blob/master/examples/php-errors.php).

<!-- source: examples/php-errors.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallablePhpErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$calls = [];
$reports = [];
$phpErrors = new CallablePhpErrorHandler(
    /**
     * Own conversion and suppression explicitly; the adapter adds neither policy.
     *
     * @param int    $code     the synthetic PHP severity
     * @param string $message  the synthetic diagnostic message
     * @param string $filename the supplied source filename
     * @param int    $line     the supplied source line
     *
     * @throws ErrorException when this callback selects an enabled error
     */
    static function (int $code, string $message, string $filename, int $line) use (&$calls): void {
        $calls[] = [$code, $message, $filename, $line];
        if (error_reporting() & $code) {
            throw ErrorException::fromPhpError($message, $code, $code, $filename, $line);
        }
    },
);
$errors = new ErrorHandler(new CallableThrowableHandler(
    /**
     * Record a converted synthetic failure without output or a real transport.
     *
     * @param Throwable $error the callback's converted error
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error;
    },
), $phpErrors);

exampleCheck([] === $calls && [] === $reports, 'Construction invoked a callback.');
$previous = error_reporting(E_ALL);

try {
    $errors->handlePhpError(E_USER_WARNING, 'Synthetic enabled input', __FILE__, 42);
    error_reporting(0);
    $errors->handlePhpError(E_USER_WARNING, 'Synthetic disabled input', __FILE__, 43);
} finally {
    error_reporting($previous);
}

exampleCheck([
    [E_USER_WARNING, 'Synthetic enabled input', __FILE__, 42],
    [E_USER_WARNING, 'Synthetic disabled input', __FILE__, 43],
] === $calls, 'The callback did not receive the four original arguments.');
exampleCheck(1 === count($reports) && $reports[0] instanceof ErrorException, 'Explicit mask/conversion policy changed.');
exampleCheck('Synthetic enabled input' === $reports[0]->getMessage(), 'Converted diagnostic metadata was lost.');

echo 'callable PHP errors: four arguments, explicit mask policy and conversion passed' . PHP_EOL;
```

## Conditional diagnostic reporting

Filter one explicit branch by PHP error severity while retaining a general diagnostic branch. A nonmatch suppresses only its delegate; it does not redact an HTTP fallback or select a response.

Source: [examples/filtering.php](https://github.com/coisa/error-handler/blob/master/examples/filtering.php).

<!-- source: examples/filtering.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\FilteringThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;

require __DIR__ . '/bootstrap.php';

$all = [];
$deprecations = [];
$allReports = new CallableThrowableHandler(
    /**
     * Retain the general diagnostic branch alongside the selected branch.
     *
     * @param Throwable $error the original synthetic failure
     */
    static function (Throwable $error) use (&$all): void {
        $all[] = $error;
    },
);
$deprecationReports = new CallableThrowableHandler(
    /**
     * Record selected events without output or a real logging transport.
     *
     * @param Throwable $error the selected original failure
     */
    static function (Throwable $error) use (&$deprecations): void {
        $deprecations[] = $error;
    },
);

$filtered = new FilteringThrowableHandler(
    $deprecationReports,
    /**
     * Select PHP deprecations by severity, not arbitrary exception codes.
     *
     * @param Throwable $error the original failure being selected
     *
     * @return bool true forwards this event to the deprecation branch
     */
    static fn(Throwable $error): bool => $error instanceof ErrorException && E_USER_DEPRECATED === $error->getSeverity(),
);
$routes = new ThrowableHandlerAggregate($allReports, $filtered);
$deprecation = new ErrorException('Synthetic deprecation', 0, E_USER_DEPRECATED);
$ordinary = new RuntimeException('Synthetic ordinary exception', E_USER_DEPRECATED);

$routes->handleThrowable($deprecation);
$routes->handleThrowable($ordinary);

exampleCheck([$deprecation, $ordinary] === $all, 'Filtering changed the general branch or object identity.');
exampleCheck([$deprecation] === $deprecations, 'Exception codes were mistaken for PHP severity.');

echo 'explicit filtering, severity selection and original-object delivery passed' . PHP_EOL;
```

## Attempt every reporter and retain all failures

Opt into ordered attempts through partial success, then handle the aggregate failure explicitly. The original throwable and every reporter failure retain their identity; the default aggregate remains fail-fast.

Source: [examples/best-effort.php](https://github.com/coisa/error-handler/blob/master/examples/best-effort.php).

<!-- source: examples/best-effort.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Exception\AggregateReportingException;
use CoiSA\ErrorHandler\Handler\BestEffortThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$original = new RuntimeException('Synthetic operation failure');
$auditFailure = new RuntimeException('Synthetic audit failure');
$backupFailure = new Error('Synthetic backup failure');
$attempts = [];

$audit = new CallableThrowableHandler(
    /**
     * Record the first attempt and fail with a distinct synthetic exception.
     *
     * @param Throwable $event the original diagnostic to report
     *
     * @throws RuntimeException the exact synthetic audit failure
     */
    static function (Throwable $event) use (&$attempts, $auditFailure): void {
        $attempts[] = ['audit', $event];

        throw $auditFailure;
    },
);

$archive = new CallableThrowableHandler(
    /**
     * Record the successful middle attempt without an external transport.
     *
     * @param Throwable $event the unchanged original diagnostic
     */
    static function (Throwable $event) use (&$attempts): void {
        $attempts[] = ['archive', $event];
    },
);

$backup = new CallableThrowableHandler(
    /**
     * Record the final attempt and fail with a distinct synthetic Error.
     *
     * @param Throwable $event the unchanged original diagnostic
     *
     * @throws Error the exact synthetic backup failure
     */
    static function (Throwable $event) use (&$attempts, $backupFailure): void {
        $attempts[] = ['backup', $event];

        throw $backupFailure;
    },
);

$reporter = new BestEffortThrowableHandlerAggregate($audit, $archive, $backup);
$recordedFailure = null;

try {
    $reporter->handleThrowable($original);
} catch (AggregateReportingException $failure) {
    $recordedFailure = [
        'original' => $failure->getOriginalThrowable(),
        'failures' => $failure->getFailures(),
    ];
}

exampleCheck([
    ['audit', $original],
    ['archive', $original],
    ['backup', $original],
] === $attempts, 'Every reporter must receive the same original once, in order.');

exampleCheck([
    'original' => $original,
    'failures' => [$auditFailure, $backupFailure],
] === $recordedFailure, 'Partial success must preserve the original and every reporter failure.');

echo 'best-effort order, original cause and all reporter failures passed' . PHP_EOL;
```

## PSR-14 dispatch, listeners and logging

Use application-owned dispatchers for raw throwables and wrapped ErrorEvent values. The complete recipe includes both callable listeners/providers and LogErrorEventListener; configuring both routes creates separate deliveries.

Source: [examples/events.php](https://github.com/coisa/error-handler/blob/master/examples/events.php).

<!-- source: examples/events.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

require __DIR__ . '/bootstrap.php';

$failure = new RuntimeException('Synthetic event failure');
$rawCalls = 0;
$wrappedCalls = 0;
$logger = new class extends AbstractLogger {
    /** @var array<int, array{level: mixed, message: string}> Synthetic in-memory log records. */
    public array $records = [];

    /**
     * Record a synthetic log entry using signatures compatible with PSR-3 1.x–3.x.
     *
     * @param mixed                $level   the selected log level
     * @param object|string        $message the string or stringable event message
     * @param array<string, mixed> $context application log context
     */
    public function log($level, $message, array $context = []): void
    {
        if (is_object($message) && method_exists($message, '__toString')) {
            $message = $message->__toString();
        }

        exampleCheck(is_string($message), 'A PSR-3 message must be a string or stringable object.');

        $this->records[] = ['level' => $level, 'message' => $message];
    }
};
$rawListeners = new ThrowableListenerProvider(new ThrowableCallableListener(
    /**
     * Verify that raw dispatch retains the original throwable.
     *
     * @param Throwable $error the raw PSR-14 event
     */
    static function (Throwable $error) use ($failure, &$rawCalls): void {
        exampleCheck($error === $failure, 'Raw event identity changed.');

        ++$rawCalls;
    },
));
$wrappedListeners = new ErrorEventListenerProvider(new ErrorEventCallableListener(
    /**
     * Verify that the wrapper exposes its original throwable and string representation.
     *
     * @param ErrorEventInterface $event the wrapped PSR-14 event
     */
    static function (ErrorEventInterface $event) use ($failure, &$wrappedCalls): void {
        exampleCheck($event->getThrowable() === $failure, 'Wrapped event identity changed.');
        exampleCheck((string) $event === (string) $failure, 'Wrapped event text changed.');

        ++$wrappedCalls;
    },
));
$wrappedListeners->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));

$providers = new ListenerProviderAggregate();
$providers->attach($rawListeners);
$providers->attach($wrappedListeners);

$dispatcher = new EventDispatcher($providers);

(new DispatchThrowableHandler($dispatcher))->handleThrowable($failure);
(new DispatchErrorEventThrowableHandler($dispatcher))->handleThrowable($failure);

exampleCheck(1 === $rawCalls && 1 === $wrappedCalls && 1 === count($logger->records), 'Event routing changed.');
exampleCheck(LogLevel::CRITICAL === $logger->records[0]['level'], 'The configured log level was ignored.');

echo 'raw throwable, ErrorEvent, listener providers and PSR-3 logging passed' . PHP_EOL;
```

## Existing container and ConfigProvider integration

Provide application services to the current ErrorHandlerContainer and ConfigProvider/Laminas maps. Service discovery stays inert; cached resolution and explicit fresh factory invocation have different lifetimes.

Source: [examples/container.php](https://github.com/coisa/error-handler/blob/master/examples/container.php).

<!-- source: examples/container.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use Laminas\ServiceManager\ServiceManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

require __DIR__ . '/bootstrap.php';

$reports = 0;
$reporter = new CallableThrowableHandler(
    /**
     * Count synthetic diagnostics without registering native callbacks.
     *
     * @param Throwable $error the synthetic composition failure
     */
    static function (Throwable $error) use (&$reports): void {
        ++$reports;
    },
);

// Applications own their parent container and its optional dependencies.
$application = new ServiceManager();

$provider = new ConfigProvider();
$configuration = $provider();

$native = new ServiceManager($provider->getDependencies());

$messages = new Psr17Factory();

$dispatcher = new EventDispatcher(new ListenerProviderAggregate());

foreach ([$application, $native] as $services) {
    $services->setService(CallableThrowableHandler::class, $reporter);
    $services->setService(EventDispatcherInterface::class, $dispatcher);
    $services->setService(ResponseFactoryInterface::class, $messages);
    $services->setService(StreamFactoryInterface::class, $messages);
}

$legacy = new ErrorHandlerContainer($application);

$process = $legacy->get(ErrorHandler::class);

exampleCheck($process instanceof ErrorHandler, 'The container returned the wrong core service.');

$process->handleThrowable(new RuntimeException('Legacy container fixture'));

exampleCheck($legacy->get(ErrorHandler::class) === $process, 'The legacy container must cache its handler.');

$fresh = (new ErrorHandlerFactory())($legacy);

exampleCheck($fresh !== $process, 'Direct factory invocation must construct a new handler.');

$fresh->handleThrowable(new RuntimeException('Fresh factory fixture'));

// A ConfigProvider-aware application can consume the same maps directly.
exampleCheck($configuration['dependencies'] === $provider->getDependencies(), 'ConfigProvider maps changed.');

$native->get(ErrorHandler::class)->handleThrowable(new RuntimeException('ConfigProvider fixture'));

foreach (array_keys($provider->getFactories()) as $service) {
    exampleCheck($legacy->get($service) instanceof $service, 'A legacy provider factory did not resolve its service.');
    exampleCheck($native->get($service) instanceof $service, 'A native provider factory did not resolve its service.');
}

exampleCheck(3 === $reports, 'One of the composition paths lost its reporter.');

echo 'legacy container, cached services, fresh factory and ConfigProvider passed' . PHP_EOL;
```

## HTTP responses, output capture and a safe application adapter

Exercise normal and failed PSR-15 processing, captured output, the legacy empty/zero diagnostic fallback and an explicit application response factory. The current middleware does not report independently before rendering; do not add a second diagnostic call around the built-in capture chain.

The example's single-line reason remains unchanged. In a checkout/release containing the [reason-phrase repair](https://github.com/coisa/error-handler/issues/89), diagnostics with forbidden status-line controls use the PSR-17 default reason instead. This does not redact the original throwable or the legacy body fallback; see [the HTTP contract](http.md#existing-response-and-output-capture).

Source: [examples/http.php](https://github.com/coisa/error-handler/blob/master/examples/http.php).

<!-- source: examples/http.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'capture';
$reports = 0;
$messages = new Psr17Factory();

$reporter = new CallableThrowableHandler(
    /**
     * Render synthetic public output or count private reporting in the safe route.
     *
     * @param Throwable $error the synthetic downstream failure
     *
     * @throws RuntimeException in the reporter-failure fixture only
     */
    static function (Throwable $error) use ($mode, &$reports): void {
        ++$reports;

        if ('capture' === $mode) {
            echo 'Public example response';
        } elseif ('zero' === $mode) {
            echo '0';
        } elseif ('reporter-failure' === $mode) {
            throw new RuntimeException('Reporter fixture failed');
        }
    },
);
$errors = new ErrorHandler($reporter);

$responses = new ThrowableResponseFactory(new ThrowableStreamFactory($messages, $reporter), $messages);

if ('safe' === $mode) {
    // This is an application adapter, not a built-in pure renderer.
    $responses = new class ($messages, $reporter) implements ThrowableResponseFactoryInterface {
        /** @var Psr17Factory the application-owned response and stream implementation */
        private Psr17Factory $messages;

        /** @var ThrowableHandlerInterface the private diagnostic reporter */
        private ThrowableHandlerInterface $reporter;

        /**
         * Keep diagnostic delivery and public response composition explicit.
         *
         * @param Psr17Factory              $messages the application HTTP factories
         * @param ThrowableHandlerInterface $reporter the private diagnostic destination
         */
        public function __construct(Psr17Factory $messages, ThrowableHandlerInterface $reporter)
        {
            $this->messages = $messages;
            $this->reporter = $reporter;
        }

        /**
         * Report once, then build a fixed public response without exception details.
         *
         * @param Throwable $error the downstream failure to report privately
         *
         * @return ResponseInterface the fixed public error response
         */
        public function createResponseFromThrowable(Throwable $error): ResponseInterface
        {
            $this->reporter->handleThrowable($error);

            return $this->messages->createResponse(500)->withBody(
                $this->messages->createStream('Internal Server Error'),
            );
        }
    };
} elseif ('renderer-failure' === $mode) {
    $responses = new class implements ThrowableResponseFactoryInterface {
        /**
         * Demonstrate cleanup when an application renderer fails.
         *
         * @param Throwable $error the downstream failure
         *
         * @return ResponseInterface no response is returned by this failing fixture
         *
         * @throws RuntimeException the synthetic renderer failure
         */
        public function createResponseFromThrowable(Throwable $error): ResponseInterface
        {
            throw new RuntimeException('Renderer fixture failed');
        }
    };
}

$next = new class ($messages, $mode) implements RequestHandlerInterface {
    /** @var Psr17Factory the application-owned HTTP factories */
    private Psr17Factory $messages;

    /** @var string the selected synthetic downstream route */
    private string $mode;

    /**
     * Store the synthetic request route.
     *
     * @param Psr17Factory $messages the application HTTP factories
     * @param string       $mode     the selected example route
     */
    public function __construct(Psr17Factory $messages, string $mode)
    {
        $this->messages = $messages;
        $this->mode = $mode;
    }

    /**
     * Return a normal PSR response or throw a synthetic downstream failure.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface the successful route's response
     *
     * @throws RuntimeException for an error-response fixture
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ('success' === $this->mode) {
            return $this->messages->createResponse(200)->withBody($this->messages->createStream('OK'));
        }

        throw new RuntimeException('Public example failure', 404);
    }
};

/** Represent a surrounding application's PHP error callback. */
$previousError = static fn(): bool => true;

/**
 * Represent a surrounding application's exception callback.
 *
 * @param Throwable $error an unexpected failure escaping middleware
 *
 * @throws LogicException if the surrounding callback is unexpectedly invoked
 */
$previousException = static function (Throwable $error): void {
    throw new LogicException('The surrounding exception callback must remain unused.', 0, $error);
};

set_error_handler($previousError);
set_exception_handler($previousException);

$middleware = new ErrorHandlerMiddleware($errors, $responses);
$bufferLevel = ob_get_level();
$response = null;
$bufferDelta = 0;

try {
    $response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);
} catch (RuntimeException $error) {
    exampleCheck(in_array($mode, ['renderer-failure', 'reporter-failure'], true), 'Unexpected HTTP fixture failure.');
    exampleCheck(false !== strpos($error->getMessage(), 'fixture failed'), 'Failure identity changed.');

    // Observe the library's exceptional cleanup before the fixture's final safety cleanup.
    $bufferDelta = ob_get_level() - $bufferLevel;
} finally {
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    $actualError = set_error_handler($previousError);
    restore_error_handler();
    $actualException = set_exception_handler($previousException);
    restore_exception_handler();

    restore_error_handler();
    restore_exception_handler();

    exampleCheck(
        $actualError === $previousError && $actualException === $previousException,
        'Middleware lost surrounding callbacks.',
    );
}

if ('renderer-failure' === $mode || 'reporter-failure' === $mode) {
    exampleCheck(null === $response, 'A failed renderer unexpectedly returned a response.');
    exampleCheck(0 === $bufferDelta, 'A failed renderer left an output buffer behind.');
} else {
    exampleCheck($response instanceof ResponseInterface, 'Middleware did not return a PSR response.');

    $body = (string) $response->getBody();

    if ('capture' === $mode) {
        exampleCheck(
            'Public example response' === $body && 404 === $response->getStatusCode(),
            'Captured response changed.',
        );
        exampleCheck(
            'Public example failure' === $response->getReasonPhrase(),
            'The existing reason phrase policy changed.',
        );
    } elseif ('safe' === $mode) {
        exampleCheck(
            'Internal Server Error' === $body && 500 === $response->getStatusCode(),
            'Safe application response changed.',
        );
    } elseif ('success' === $mode) {
        exampleCheck('OK' === $body && 200 === $response->getStatusCode(), 'Normal response changed.');
    } else {
        exampleCheck(in_array($mode, ['empty', 'zero'], true), 'Unknown HTTP example mode.');
        exampleCheck(
            false !== strpos($body, 'Public example failure') && false !== strpos($body, 'RuntimeException'),
            'The existing fallback changed.',
        );
    }
}

exampleCheck(
    ('success' === $mode || 'renderer-failure' === $mode ? 0 : 1) === $reports,
    'Unexpected reporting count.',
);

echo 'HTTP ' . $mode . ': response/reporting contract and callback restoration passed; buffer delta '
    . $bufferDelta . PHP_EOL;
```

## Pure JSON with one private report

Use explicit JSON and reporting adapters without changing the legacy middleware or default renderer. Both modes preserve integer 503 and report the original privately once. Public output contains only the controlled error; explicitly selected development output adds metadata-only trace and no-store. The CLI choice represents trusted configuration. No output buffer, diagnostic echo or HTTP emission is required.

Source: [examples/http-json.php](https://github.com/coisa/error-handler/blob/master/examples/http-json.php).

<!-- source: examples/http-json.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

$messages = new Psr17Factory();
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'debug'], true), 'Choose the public or debug example mode.');

// This explicit CLI fixture choice stands in for trusted application configuration.
$debug = 'debug' === $mode;

/** @return RuntimeException a synthetic downstream failure with a native call frame */
$createFailure = static fn(): RuntimeException => new RuntimeException('Private synthetic upstream details', 503);

$original = $createFailure();
$reports = [];

$reporter = new CallableThrowableHandler(
    /**
     * Record the original privately without printing a public diagnostic.
     *
     * @param Throwable $error the original downstream failure
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error; // Replace this recording sink with the application's private transport.
    },
);

$errors = new ErrorHandler($reporter);

$json = new JsonThrowableResponseFactory($messages, $messages, $debug);

$responses = new ReportingThrowableResponseFactory($reporter, $json);

$middleware = new ErrorHandlerMiddleware($errors, $responses);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic downstream failure */
    private Throwable $original;

    /**
     * Store the original event for the synthetic application route.
     *
     * @param Throwable $original the event the middleware will catch
     */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * Fail the route without printing diagnostics or emitting a response.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface no response is returned by this failing route
     *
     * @throws Throwable the original synthetic failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$bufferLevel = ob_get_level();
$response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);

exampleCheck([$original] === $reports, 'Expected exactly one private report of the original event.');
exampleCheck(503 === $response->getStatusCode(), 'The integer HTTP error code was not preserved.');
exampleCheck('application/json' === $response->getHeaderLine('Content-Type'), 'Expected a JSON representation.');
if ($debug) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    exampleCheck('Request failed' === $payload['error'], 'The public error label changed.');
    exampleCheck(
        $original->getMessage() === $payload['exception']['message'] && [] !== $payload['exception']['trace'],
        'Explicit development metadata is missing.',
    );
    foreach ($payload['exception']['trace'] as $frame) {
        exampleCheck(
            !array_key_exists('args', $frame) && !array_key_exists('object', $frame),
            'Private trace arguments or objects must not be serialized.',
        );
    }
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Debug details must not be cached.');
} else {
    exampleCheck(
        '{"error":"Request failed"}' === (string) $response->getBody(),
        'The generic JSON body must not contain the private diagnostic.',
    );
}
exampleCheck(
    $original->getMessage() !== $response->getReasonPhrase(),
    'The diagnostic message must not become an HTTP reason phrase.',
);
exampleCheck($bufferLevel === ob_get_level(), 'JSON composition unexpectedly changed the buffer stack.');

// The application owns response emission; this CLI recipe prints only its verification summary.
echo 'HTTP JSON ' . $mode . ': explicit payload, status preservation and one private report passed' . PHP_EOL;
```

## Optional Interop JSON profile

Use the additive JSON profile with application-owned PSR-17 and a private reporter. Each provider declares only its own services: select JSON before the separate base provider so its response alias wins over legacy capture. The base supplies lifecycle/defaults and scope factories; JSON supplies reporting plus its pure renderer. This integration requires PHP 8.3+ and FastForward installed in the application's runtime dependencies. Config supplies an explicit bool before container construction. Each scope-factory invocation creates a distinct handler and middleware; existing 2.x shutdown-queue limits remain. Select these two modes with the existing verifier's `--with-fast-forward` option.

Source: [examples/provider-json.php](https://github.com/coisa/error-handler/blob/master/examples/provider-json.php).

<!-- source: examples/provider-json.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Config\config;
use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

exampleCheck(
    PHP_VERSION_ID >= 80300 && function_exists('FastForward\Container\container'),
    'Install fast-forward/container:^1.9 in the application and use PHP 8.3 or newer.',
);
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'debug'], true), 'Choose the public or debug example mode.');

// This CLI choice stands in for trusted application configuration, never request headers.
$settings = config(['error_handler' => ['json' => ['debug' => 'debug' === $mode]]]);
$messages = new Psr17Factory();
$original = new RuntimeException('Private synthetic provider failure', 503);
$reports = [];
$reporter = new CallableThrowableHandler(
    /**
     * Record one original event without writing diagnostics into a public response.
     *
     * @param Throwable $event the original downstream failure
     */
    static function (Throwable $event) use (&$reports): void {
        $reports[] = $event; // Replace this recording sink with the application's private transport.
    },
);

$application = new ArrayServiceProvider([
    ResponseFactoryInterface::class => new ServiceFactory($messages),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($reporter),
]);

// Separate providers are first-wins: the JSON selection must precede the base capture alias.
if ('debug' === $mode) {
    $services = container(
        new JsonErrorHandlerServiceProvider($settings->get('error_handler.json.debug', false)),
        ErrorHandlerServiceProvider::class,
        $application,
    );
} else {
    $services = container(
        JsonErrorHandlerServiceProvider::class,
        ErrorHandlerServiceProvider::class,
        $application,
    );
}

// Cache the factory, then invoke it for a distinct handler and wrapper for each HTTP scope.
$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);

$middleware = $middlewareFactory($services);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic downstream failure */
    private Throwable $original;

    /**
     * Retain original identity for the private reporter.
     *
     * @param Throwable $original the event that the middleware will catch
     */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * Fail a synthetic route without emission or registration of its own callbacks.
     *
     * @param ServerRequestInterface $request the synthetic HTTP request
     *
     * @return ResponseInterface no response is returned by this failing route
     *
     * @throws Throwable the original downstream failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$response = $middleware->process($messages->createServerRequest('GET', '/provider-json'), $next);

exampleCheck([$original] === $reports, 'The profile must report the exact original once.');
exampleCheck(503 === $response->getStatusCode(), 'Expected the native HTTP error code.');
exampleCheck('application/json' === $response->getHeaderLine('Content-Type'), 'Expected JSON.');

if ('debug' === $mode) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    exampleCheck(
        $original->getMessage() === $payload['exception']['message'],
        'Trusted debug configuration did not reach the renderer.',
    );
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
} else {
    exampleCheck('{"error":"Request failed"}' === (string) $response->getBody(), 'Public JSON leaked diagnostics.');
}

// Applications emit the response; this CLI fixture prints only its verification summary.
echo 'Interop JSON ' . $mode . ': configured profile, fresh scope and one private report passed' . PHP_EOL;
```

Continue with the [core API](api.md), [container integration](container.md), [HTTP composition](http.md) and [testing guide](testing.md). Those guides explain lifecycle ownership, reporting failures and the current output-capture limits.

## Optional development HTML with Whoops

Install the optional application dependency described in the [HTTP guide](http.md#opt-in-development-html-with-whoops). Explicit trusted development configuration selects a fresh configured page; public mode selects generic JSON without constructing Whoops. No request negotiation or response emission is performed. Selected hidden keys are not comprehensive redaction.

Source: [examples/http-whoops.php](https://github.com/coisa/error-handler/blob/master/examples/http-whoops.php).

<!-- source: examples/http-whoops.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;

require __DIR__ . '/bootstrap.php';

$messages = new Psr17Factory();
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'development'], true), 'Choose public or development mode.');

// This CLI fixture represents trusted configuration for an explicitly selected HTML pipeline.
// A request header or query parameter must never enable development diagnostics.
$debug = 'development' === $mode;
$pageCalls = 0;
$reports = [];
$original = new RuntimeException('<synthetic-development-detail>', 503);

$reporter = new CallableThrowableHandler(
    /** @param Throwable $error the unchanged diagnostic delivered privately */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error; // Replace this recording sink with the application's private transport.
    },
);

$renderer = new JsonThrowableResponseFactory($messages, $messages);

if ($debug) {
    $renderer = new WhoopsThrowableResponseFactory(
        $messages,
        $messages,
        /** @return PrettyPageHandler a new page using the application's explicit field-hiding policy */
        static function () use (&$pageCalls): PrettyPageHandler {
            ++$pageCalls;
            $page = new PrettyPageHandler();

            $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
            $page->hideSuperglobalKey('_COOKIE', 'session');
            $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

            return $page;
        },
    );
}

exampleCheck(0 === $pageCalls, 'Construction must not create a page.');

$responses = new ReportingThrowableResponseFactory($reporter, $renderer);

$errors = new ErrorHandler($reporter);

$middleware = new ErrorHandlerMiddleware($errors, $responses);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic application failure */
    private Throwable $original;

    /** @param Throwable $original the unchanged failure thrown by the route */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * @param ServerRequestInterface $request the synthetic application request
     *
     * @return ResponseInterface this failing route returns no normal response
     *
     * @throws Throwable the unchanged application failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$bufferLevel = ob_get_level();
$response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);

exampleCheck([$original] === $reports, 'Expected one private report of the original failure.');
exampleCheck(503 === $response->getStatusCode(), 'Expected the original integer HTTP status.');
exampleCheck($bufferLevel === ob_get_level(), 'The renderer changed the caller buffer stack.');
exampleCheck($original->getMessage() !== $response->getReasonPhrase(), 'The diagnostic became a reason phrase.');

if ($debug) {
    exampleCheck(1 === $pageCalls, 'Expected one fresh configured page.');
    exampleCheck('text/html; charset=UTF-8' === $response->getHeaderLine('Content-Type'), 'Expected HTML.');
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
    exampleCheck(
        false !== strpos((string) $response->getBody(), '&lt;synthetic-development-detail&gt;'),
        'Expected an escaped diagnostic in the development page.',
    );
} else {
    exampleCheck(0 === $pageCalls, 'Public mode must not construct a Whoops page.');
    exampleCheck('application/json' === $response->getHeaderLine('Content-Type'), 'Expected public JSON.');
    exampleCheck('{"error":"Request failed"}' === (string) $response->getBody(), 'Expected the generic public body.');
}

// No response is emitted. Hiding selected keys is not complete redaction or production authorization.
echo 'HTTP Whoops ' . $mode . ': explicit renderer selection and one private report passed' . PHP_EOL;
```


## Optional Interop Whoops profile

With PHP 8.3+, install FastForward as an application runtime dependency and optional Whoops ^2.18.4 as described in the [provider guide](container.md#development-html-profile). Trusted application configuration selects this diagnostic HTML profile. Compose the independent providers in one aggregate ordered base, Whoops, application. The provider has no constructor arguments; its default page service is lazy and cached. The configured mode reads Config and binds a prepared `PrettyPageHandler` through `ServiceFactory`. Both use private reporting and two distinct middleware scopes, without negotiation or emission. Sequential renders retain page configuration while replacing the current diagnostic. The guide records container fallback and missing-package limits.

Source: [examples/provider-whoops.php](https://github.com/coisa/error-handler/blob/master/examples/provider-whoops.php).

<!-- source: examples/provider-whoops.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;

use function FastForward\Config\config;
use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

exampleCheck(
    PHP_VERSION_ID >= 80300 && function_exists('FastForward\Container\container'),
    'Install fast-forward/container:^1.9 in the application and use PHP 8.3 or newer.',
);

// FastForward integration requires PHP 8.3+ and an application runtime dependency.
// Selecting this profile deliberately chooses diagnostic HTML; no request enables it.
$mode = $argv[1] ?? 'default';
exampleCheck(in_array($mode, ['default', 'configured'], true), 'Choose default or configured mode.');

$messages = new Psr17Factory();
$reports = [];
$pageCalls = 0;

$privateReporter = new CallableThrowableHandler(
    /** @param Throwable $event the unchanged application failure delivered privately */
    static function (Throwable $event) use (&$reports): void {
        $reports[] = $event; // Replace with the application's private log transport.
    },
);

$factories = [
    ResponseFactoryInterface::class => new ServiceFactory($messages),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($privateReporter),
];

if ('configured' === $mode) {
    $settings = config([
        'development' => [
            'page_title' => 'Synthetic application diagnostics',
        ],
    ]);

    // Prepare configuration outside get(): factory failures may trigger container fallback.
    $page = new PrettyPageHandler();
    ++$pageCalls;

    $page->setPageTitle($settings->get('development.page_title'));
    $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
    $page->hideSuperglobalKey('_COOKIE', 'session');
    $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

    $factories[PrettyPageHandler::class] = new ServiceFactory($page);
}

$application = new ArrayServiceProvider($factories);

// One aggregate selects HTML explicitly and applies the application's page override.
$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    new WhoopsErrorHandlerServiceProvider(),
    $application,
));

$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);
exampleCheck($middlewareFactory instanceof ErrorHandlerMiddlewareFactory, 'Expected the fresh-scope factory.');

$firstScope = $middlewareFactory($services);
$secondScope = $middlewareFactory($services);

exampleCheck(
    ('configured' === $mode ? 1 : 0) === $pageCalls,
    'Scope construction must not create an additional page.',
);
exampleCheck($firstScope !== $secondScope, 'Each HTTP scope needs a new middleware and handler.');

$failures = [
    new RuntimeException(base64_encode('First synthetic provider diagnostic'), 503),
    new RuntimeException(base64_encode('Second synthetic provider diagnostic'), 404),
];

/**
 * Creates a synthetic route for the selected application failure.
 *
 * @param Throwable $failure the original failure for this HTTP scope
 *
 * @return RequestHandlerInterface the failing application route
 */
function exampleWhoopsRoute(Throwable $failure): RequestHandlerInterface
{
    return new class ($failure) implements RequestHandlerInterface {
        /** @var Throwable the unchanged synthetic route failure */
        private Throwable $failure;

        /** @param Throwable $failure the application error thrown in this request */
        public function __construct(Throwable $failure)
        {
            $this->failure = $failure;
        }

        /**
         * @param ServerRequestInterface $request the synthetic application request
         *
         * @return ResponseInterface this route never produces a normal response
         *
         * @throws Throwable the unchanged application failure
         */
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw $this->failure;
        }
    };
}

$first = $firstScope->process(
    $messages->createServerRequest('GET', '/first'),
    exampleWhoopsRoute($failures[0]),
);

$second = $secondScope->process(
    $messages->createServerRequest('GET', '/second'),
    exampleWhoopsRoute($failures[1]),
);

exampleCheck($failures === $reports, 'Each original must reach the private reporter once.');
exampleCheck(503 === $first->getStatusCode() && 404 === $second->getStatusCode(), 'Expected original HTTP codes.');
exampleCheck('text/html; charset=UTF-8' === $first->getHeaderLine('Content-Type'), 'Expected HTML.');
exampleCheck('no-store' === $first->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
exampleCheck(
    false === strpos((string) $second->getBody(), $failures[0]->getMessage()),
    'The next page must not retain the first diagnostic.',
);

if ('configured' === $mode) {
    exampleCheck(1 === $pageCalls, 'Expected one cached configured page for sequential renders.');
    exampleCheck(
        false !== strpos((string) $first->getBody(), '<title>Synthetic application diagnostics</title>'),
        'Expected the application-configured title.',
    );
    exampleCheck(
        false !== strpos((string) $second->getBody(), '<title>Synthetic application diagnostics</title>'),
        'The cached page must retain application configuration.',
    );
}

// No HTTP emission. Hiding selected keys is not comprehensive redaction.
echo 'Whoops provider ' . $mode . ': lazy pages, fresh scopes and private reporting passed' . PHP_EOL;
```

