# Executable major cookbook

This is the unreleased PHP 8.3+ development line. The checkout includes Nyholm PSR-7 and Phly EventDispatcher for examples; applications own their PSR implementations and Composer lockfiles. FastForward Container is a required runtime dependency. See [example setup](../examples/README.md).

Run `php examples/verify.php` to verify every recipe and documented mode in bounded children. The CLI recipe deliberately exits 1. The [static site](index.html#examples) links these programs and displays checked excerpts. Every complete entry is checked byte-for-byte against source, omitting only its leading license header. Duplicate, changed or missing recipes fail validation; examples do not prove delivery to real transports.

<a id="cli"></a>
## Process bootstrap

Report a warning and an uncaught exception, then exit 1. [Source](../examples/cli.php).

<!-- source: examples/cli.php -->
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

<!-- source: examples/fast-forward.php -->
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

<!-- source: examples/reporters.php -->
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

Exercise original Throwable and ErrorEvent notification paths with only an
application dispatcher. Expect one raw delivery, one wrapped delivery and
`Private log records: 1`; exit 0 alone does not prove reporting occurred.
See [optional discovery and fallback](fast-forward.md) before relying on the
default reporter. [Source](../examples/event-dispatcher.php).

<!-- source: examples/event-dispatcher.php -->
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

<!-- source: examples/http-middleware.php -->
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

<!-- source: examples/http-rendering.php -->
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

<!-- source: examples/custom-handlers.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use FastForward\Container\Factory\ServiceFactory;

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

/** Records the explicit notification on the active lifecycle scope. */
$shutdown = static function () use (&$shutdowns): void {
    ++$shutdowns;
};

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
    ShutdownHandlerInterface::class => new ServiceFactory($shutdown),
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

<!-- source: examples/scopes.php -->
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

<!-- source: examples/output-capture.php -->
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

<a id="filtering"></a>
## Filtering

Select per invocation without changing the event or defaults. [Source](../examples/filtering.php).

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

Pass three callbacks directly to ErrorHandler, retaining four original PHP-error arguments,
application-owned mask policy and explicit active shutdown notification. [Source](../examples/php-errors.php).

<!-- source: examples/php-errors.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Exception\ErrorException;

require __DIR__ . '/bootstrap.php';

$calls = [];
$reports = [];
$shutdowns = 0;
$errors = new ErrorHandler(
    /**
     * Record a converted synthetic failure without output or a real transport.
     *
     * @param Throwable $error the callback's converted error
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error;
    },
    /**
     * Own conversion and suppression explicitly; normalization adds neither policy.
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
    /** Count explicit active shutdown notifications without recovering or terminating a process. */
    static function () use (&$shutdowns): void {
        ++$shutdowns;
    },
);

exampleCheck([] === $calls && [] === $reports && 0 === $shutdowns, 'Construction invoked a callback.');
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

$errors->handleShutdown();
exampleCheck(0 === $shutdowns, 'An inactive handler invoked the shutdown policy.');
$errors->register();

try {
    $errors->handleShutdown();
} finally {
    $errors->unregister();
}

exampleCheck(1 === $shutdowns, 'The active shutdown callback was not invoked once.');

echo 'callable PHP errors: four arguments, explicit mask policy and conversion passed' . PHP_EOL;
```

<a id="best-effort"></a>
## Best-effort reporting

Attempt every fixed slot in order, then retain the original and all reporter failures. [Source](../examples/best-effort.php).

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

## Built-in JSON responses

Public and explicitly enabled debug JSON preserve HTTP error codes. Middleware reports privately once, then uses the pure factory. [Source](../examples/http-json.php).

<!-- source: examples/http-json.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
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

$middleware = new ErrorHandlerMiddleware($errors, $json);

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

## Explicit development HTML

Trusted configuration selects pure JSON or optional Whoops HTML. The application owns diagnostic policy; no request enables it. [Source](../examples/http-whoops.php).

<!-- source: examples/http-whoops.php -->
```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
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

$errors = new ErrorHandler($reporter);

$middleware = new ErrorHandlerMiddleware($errors, $renderer);

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
        str_contains((string) $response->getBody(), '&lt;synthetic-development-detail&gt;'),
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

## JSON service-provider profile

Compose base, JSON and application maps explicitly, with a real bool for debug and a fresh middleware scope. [Source](../examples/provider-json.php).

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
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
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
    'Load the FastForward runtime dependency of this source on PHP 8.3 or newer.',
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

// Merge the required reporter and renderer after library defaults in one provider.
if ('debug' === $mode) {
    $services = container(new AggregateServiceProvider(
        new ErrorHandlerServiceProvider(),
        new JsonErrorHandlerServiceProvider($settings->get('error_handler.json.debug', false)),
        $application,
    ));
} else {
    $services = container(new AggregateServiceProvider(
        new ErrorHandlerServiceProvider(),
        new JsonErrorHandlerServiceProvider(),
        $application,
    ));
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

## Whoops service-provider profile

Compose independent maps in one aggregate, configure the page service and reuse it sequentially while creating distinct request scopes. [Source](../examples/provider-whoops.php).

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
    'Load the FastForward runtime dependency of this source on PHP 8.3 or newer.',
);

// FastForward is a required runtime dependency of this PHP 8.3+ major.
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
$route = static function (Throwable $failure): RequestHandlerInterface {
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
};

$first = $firstScope->process(
    $messages->createServerRequest('GET', '/first'),
    $route($failures[0]),
);

$second = $secondScope->process(
    $messages->createServerRequest('GET', '/second'),
    $route($failures[1]),
);

exampleCheck($failures === $reports, 'Each original must reach the private reporter once.');
exampleCheck(503 === $first->getStatusCode() && 404 === $second->getStatusCode(), 'Expected original HTTP codes.');
exampleCheck('text/html; charset=UTF-8' === $first->getHeaderLine('Content-Type'), 'Expected HTML.');
exampleCheck('no-store' === $first->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
exampleCheck(
    !str_contains((string) $second->getBody(), $failures[0]->getMessage()),
    'The next page must not retain the first diagnostic.',
);

if ('configured' === $mode) {
    exampleCheck(1 === $pageCalls, 'Expected one cached configured page for sequential renders.');
    exampleCheck(
        str_contains((string) $first->getBody(), '<title>Synthetic application diagnostics</title>'),
        'Expected the application-configured title.',
    );
    exampleCheck(
        str_contains((string) $second->getBody(), '<title>Synthetic application diagnostics</title>'),
        'The cached page must retain application configuration.',
    );
}

// No HTTP emission. Hiding selected keys is not comprehensive redaction.
echo 'Whoops provider ' . $mode . ': lazy pages, fresh scopes and private reporting passed' . PHP_EOL;
```

## Failure and lifetime boundaries

Middleware requires lifecycle and reporting on one scoped object. It reports once before a separate pure renderer; reporter failure propagates without rendering. Generic output ignores reporter bytes, so diagnostics must use private sinks.

Unregister in reverse order and use distinct nested instances. Failed-reporter suppression applies within a registered lifecycle; independent direct calls remain usable. Arbitrary exit/die, engine fatals, callback side effects and concurrent native callback scopes are not recovered. See [provider](fast-forward.md), [API](api.md), [migration](migration.md) and [testing](testing.md).

The explicit logging, filtering, callable PHP-error and best-effort adapters are retained from delivered 2.x APIs. The default aggregate remains fail-fast; no implicit adapter discovery is added. The JSON/developer-HTML adapters and independent profiles delivered in 2.5.0 are retained with pure renderers after this major's middleware report. ErrorHandler accepts matching callables directly while preserving interface precedence, default policies and explicit lifecycle ownership.
