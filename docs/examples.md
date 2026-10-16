# Executable major cookbook

This is the unreleased PHP 8.3+ development line. The checkout includes Nyholm PSR-7 and Phly EventDispatcher for examples; applications own their PSR implementations and Composer lockfiles. FastForward Container is a required runtime dependency. See [example setup](../examples/README.md).

Run `php examples/verify.php` to verify all thirteen recipes in bounded children. The CLI recipe deliberately exits 1. The same sources appear on the [static site](index.html#examples). Source parity is checked; examples do not prove delivery to real transports.

<a id="cli"></a>
## Process bootstrap

Report a warning and an uncaught exception, then exit 1. [Source](../examples/cli.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    fwrite(STDERR, $error::class . ': ' . $error->getMessage() . PHP_EOL);
});

$handler = new ErrorHandler($reporter);

$handler->register();

trigger_error('Example warning; execution continues.', E_USER_WARNING);

throw new RuntimeException('Example uncaught exception; exit status is 1.');
```

<a id="fast-forward"></a>
## Interop service provider

Resolve real FastForward definitions without installing callbacks during lookup. [Source](../examples/fast-forward.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$application = new class implements ServiceProviderInterface {
    /**
     * Supplies application service definitions for the integration fixture.
     *
     * @return array<class-string, callable> application service factories
     */
    public function getFactories(): array
    {
        return [
            CallableThrowableHandler::class => static fn(): CallableThrowableHandler => new CallableThrowableHandler(
                /**
                 * Prints only this CLI recipe's synthetic diagnostic.
                 *
                 * @param Throwable $error the converted warning supplied for reporting
                 */
                static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                },
            ),
        ];
    }

    /**
     * Declares no application service extensions.
     *
     * @return array<string, callable> an empty extension map
     */
    public function getExtensions(): array
    {
        return [];
    }
};

$services = container(ErrorHandlerServiceProvider::class, $application);

/**
 * @var ErrorHandlerInterface $errors
 */
$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('FastForward provider warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}
```

<a id="reporters"></a>
## Callable reporters and ordered composition

Deliver the same throwable in order; the existing aggregate remains fail-fast. [Source](../examples/reporters.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;

require __DIR__ . '/bootstrap.php';

$deliveries = [];
$reporters = new ThrowableHandlerAggregate(
    new CallableThrowableHandler(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'first: ' . $error->getMessage();
    }),
);

$reporters->attach(new CallableThrowableHandler(
    static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'second: ' . $error->getMessage();
    },
));

$errors = new ErrorHandler($reporters);

// Direct delivery needs no registration and does not terminate the process.
$errors->handleThrowable(new RuntimeException('Synthetic failure'));

echo implode(PHP_EOL, $deliveries) . PHP_EOL;
```

<a id="events"></a>
## PSR-14 dispatch and PSR-3 event logging

Exercise original Throwable and ErrorEvent notification paths. [Source](../examples/event-dispatcher.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$deliveries = [];

$logger = new class extends AbstractLogger {
    /**
     * @var list<string> synthetic private log records; replace with an application logger
     */
    public array $records = [];

    /**
     * Records diagnostics without emitting them into an HTTP response.
     *
     * Untyped parameters also support applications using psr/log 1.x.
     *
     * @param mixed                $level   the PSR-3 log level
     * @param mixed                $message the diagnostic message
     * @param array<string, mixed> $context the application log context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = $level . ': ' . (string) $message;
    }
};

$throwables = new ThrowableListenerProvider(
    new ThrowableCallableListener(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'throwable: ' . $error->getMessage();
    }),
);
$events = new ErrorEventListenerProvider(
    new ErrorEventCallableListener(static function (ErrorEventInterface $event) use (&$deliveries): void {
        $deliveries[] = 'event: ' . $event->getThrowable()->getMessage();
    }),
);
$events->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));

$dispatcher = new EventDispatcher(new ListenerProviderAggregate($throwables, $events));

$application = new ArrayServiceProvider([
    EventDispatcherInterface::class => static fn(): EventDispatcherInterface => $dispatcher,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('Synthetic warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}

echo implode(PHP_EOL, $deliveries) . PHP_EOL;
printf("Private log records: %d\n", count($logger->records));
```

<a id="http-middleware"></a>
## PSR-15 middleware with the provider

Report once, render a generic response and restore the process handler. [Source](../examples/http-middleware.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$reported = [];

$psr17 = new Psr17Factory();

$reporter = new CallableThrowableHandler(static function (Throwable $error) use (&$reported): void {
    $reported[] = $error; // Replace with a private logger; do not echo diagnostics.
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn(): CallableThrowableHandler => $reporter,
    ResponseFactoryInterface::class => static fn(): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn(): StreamFactoryInterface => $psr17,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$process = $services->get(ErrorHandlerInterface::class);
$middleware = $services->get(ErrorHandlerMiddleware::class);

$downstream = new class implements RequestHandlerInterface {
    /**
     * Simulates an application failure with an HTTP error status.
     *
     * @param ServerRequestInterface $request the incoming request
     *
     * @return ResponseInterface a response supplied by the error middleware
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Private database diagnostic', 503);
    }
};

$process->register();

try {
    $response = $middleware->process($psr17->createServerRequest('GET', 'https://example.test'), $downstream);

    // The process handler is active again after the middleware scope ends.
    trigger_error('Synthetic post-request warning', E_USER_WARNING);
} finally {
    $process->unregister();
}

printf("HTTP %d | %s\n", $response->getStatusCode(), (string) $response->getBody());
printf("Reported: %d\n", count($reported));

// Your HTTP framework/emitter sends the response; this example only inspects it.
```

<a id="http-rendering"></a>
## Explicit JSON streams

Return application-owned JSON without echo or buffering. [Source](../examples/http-rendering.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

$privateDiagnostics = [];
$reporter = new CallableThrowableHandler(
    static function (Throwable $error) use (&$privateDiagnostics): void {
        $privateDiagnostics[] = $error; // Replace with a private logger; emit no response body.
    },
);

$psr17 = new Psr17Factory();
$streams = new class ($psr17) implements ThrowableStreamFactoryInterface {
    /**
     * Stores the application's PSR-17 factory.
     *
     * @param StreamFactoryInterface $streams creates public response body streams
     */
    public function __construct(private StreamFactoryInterface $streams)
    {
    }

    /**
     * Returns controlled JSON directly, independently of diagnostic reporting.
     *
     * @param Throwable $throwable the private failure whose details stay undisclosed
     *
     * @return StreamInterface a public JSON body without echo or output buffering
     */
    public function createStreamFromThrowable(Throwable $throwable): StreamInterface
    {
        return $this->streams->createStream(
            json_encode(['error' => 'Service unavailable'], JSON_THROW_ON_ERROR),
        );
    }
};

$responses = new ThrowableResponseFactory($streams, $psr17);
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $responses);

$downstream = new class implements RequestHandlerInterface {
    /**
     * Simulates a private application failure with an HTTP error code.
     *
     * @param ServerRequestInterface $request the incoming request
     *
     * @return ResponseInterface the error response produced by middleware
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Private credentials', 503);
    }
};

$response = $middleware->process($psr17->createServerRequest('GET', '/'), $downstream)
    ->withHeader('Content-Type', 'application/json')
;

printf("HTTP %d | %s\n", $response->getStatusCode(), $response->getHeaderLine('Content-Type'));
echo (string) $response->getBody() . PHP_EOL;
printf("Private reports: %d\n", count($privateDiagnostics));
```

<a id="custom-handlers"></a>
## Application PHP-error and shutdown handlers

Supply explicit interface implementations and a shutdown callback. [Source](../examples/custom-handlers.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$phpErrors = new class implements PhpErrorHandlerInterface {
    /**
     * @var list<string> reportable PHP messages received by this application handler
     */
    public array $messages = [];

    /**
     * Records reportable PHP errors without converting them to exceptions.
     *
     * @param int    $code     the PHP severity
     * @param string $message  the PHP message
     * @param string $filename the source filename
     * @param int    $line     the source line
     */
    public function handlePhpError(int $code, string $message, string $filename, int $line): void
    {
        if (($code & error_reporting()) !== 0) {
            $this->messages[] = $message;
        }
    }
};

$shutdowns = 0;
$shutdown = new CallableShutdownHandler(static function () use (&$shutdowns): void {
    ++$shutdowns;
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn(): CallableThrowableHandler => new CallableThrowableHandler(
        /**
         * Routes diagnostics to the application's private sink.
         *
         * @param Throwable $error the original diagnostic
         */
        static function (Throwable $error): void {
            error_log($error->getMessage());
        },
    ),
    PhpErrorHandlerInterface::class => static fn(): PhpErrorHandlerInterface => $phpErrors,
    ShutdownHandlerInterface::class => static fn(): ShutdownHandlerInterface => $shutdown,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('Application-owned warning', E_USER_WARNING);
    $errors->handleShutdown(); // Simulate a notification without terminating the process.
} finally {
    $errors->unregister();
}

printf("PHP errors: %d | Shutdown notifications: %d\n", count($phpErrors->messages), $shutdowns);
```

<a id="scopes"></a>
## Fresh handlers and nested middleware

Use factory services for a new scope; ordinary lookups remain cached. [Source](../examples/scopes.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$reported = 0;

$psr17 = new Psr17Factory();

$reporter = new CallableThrowableHandler(static function () use (&$reported): void {
    ++$reported;
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn(): CallableThrowableHandler => $reporter,
    ResponseFactoryInterface::class => static fn(): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn(): StreamFactoryInterface => $psr17,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$makeHandler = $services->get(ErrorHandlerFactory::class);
$first = $makeHandler($services);
$second = $makeHandler($services);

$makeMiddleware = $services->get(ErrorHandlerMiddlewareFactory::class);
$outer = $makeMiddleware($services);
$inner = $makeMiddleware($services);

$downstream = new class implements RequestHandlerInterface {
    /**
     * Throws a synthetic downstream failure for the inner scope.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface the response produced by the inner middleware
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Nested failure');
    }
};

$next = new class ($inner, $downstream) implements RequestHandlerInterface {
    /**
     * Stores the independently constructed inner middleware and application handler.
     *
     * @param MiddlewareInterface     $inner      the inner error middleware
     * @param RequestHandlerInterface $downstream the application request handler
     */
    public function __construct(private MiddlewareInterface $inner, private RequestHandlerInterface $downstream)
    {
    }

    /**
     * Enters the inner scope while the outer scope remains registered.
     *
     * @param ServerRequestInterface $request the incoming request
     *
     * @return ResponseInterface the inner response
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inner->process($request, $this->downstream);
    }
};

$process = $services->get(ErrorHandlerInterface::class);

$process->register();

try {
    $response = $outer->process($psr17->createServerRequest('GET', 'https://example.test'), $next);
    trigger_error('Process handler restored', E_USER_WARNING);
} finally {
    $process->unregister();
}

printf(
    "Fresh handlers: %s | Fresh middleware: %s\n",
    $first !== $second ? 'yes' : 'no',
    $outer !== $inner ? 'yes' : 'no',
);
printf("HTTP %d | Reports: %d\n", $response->getStatusCode(), $reported);
```

<a id="output-capture"></a>
## Explicit public output capture

Capture a separate public renderer, preserving the string zero. [Source](../examples/output-capture.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/bootstrap.php';

// Optional compatibility adapter: only deliberately public output belongs here.
// Middleware now reports privately before rendering; do not inject its reporter again.
$publicRenderer = new CallableThrowableHandler(static function (): void {
    echo 'Public failure page';
});

$streams = new ThrowableStreamFactory(new Psr17Factory(), $publicRenderer);

$body = $streams->createStreamFromThrowable(new RuntimeException('Private diagnostics'));

printf("Captured body: %s\n", (string) $body);
```

<a id="logging"></a>
## Direct PSR-3 logging

Log exact messages with the original exception context. [Source](../examples/logging.php).

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

<a id="filtering"></a>
## Filtering

Select per invocation without changing the event or defaults. [Source](../examples/filtering.php).

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
    static fn(Throwable $error): bool => $error instanceof ErrorException
        && E_USER_DEPRECATED === $error->getSeverity(),
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

<a id="php-errors"></a>
## Callable PHP-error conversion

Keep four original arguments and application-owned mask policy. [Source](../examples/php-errors.php).

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
exampleCheck(
    1 === count($reports) && $reports[0] instanceof ErrorException,
    'Explicit mask/conversion policy changed.',
);
exampleCheck('Synthetic enabled input' === $reports[0]->getMessage(), 'Converted diagnostic metadata was lost.');

echo 'callable PHP errors: four arguments, explicit mask policy and conversion passed' . PHP_EOL;
```

<a id="best-effort"></a>
## Best-effort reporting

Attempt every fixed slot in order, then retain the original and all reporter failures. [Source](../examples/best-effort.php).

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

## Failure and lifetime boundaries

Middleware requires lifecycle and reporting on one scoped object. It reports once before a separate pure renderer; reporter failure propagates without rendering. Generic output ignores reporter bytes, so diagnostics must use private sinks.

Unregister in reverse order and use distinct nested instances. Failed-reporter suppression applies within a registered lifecycle; independent direct calls remain usable. Arbitrary exit/die, engine fatals, callback side effects and concurrent native callback scopes are not recovered. See [provider](fast-forward.md), [API](api.md), [migration](migration.md) and [testing](testing.md).

The explicit logging, filtering, callable PHP-error and best-effort adapters are retained from delivered 2.x APIs. The default aggregate remains fail-fast; no implicit adapter discovery is added. JSON/developer-HTML adapters remain separate proposals (#7/#8); native callable constructor unions remain planned in #70.
