# Complete 2.x cookbook

These complete recipes use the existing `coisa/error-handler` 2.x API and PHP 7.4-compatible syntax. They match the executable sources in this checkout; verify that an installed release contains the adapters you select. They do not describe the proposed major's FastForward provider, constructor callables or report-before-render middleware.

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

Continue with the [core API](api.md), [container integration](container.md), [HTTP composition](http.md) and [testing guide](testing.md). Those guides explain lifecycle ownership, reporting failures and the current output-capture limits.

