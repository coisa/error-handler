# Usage cookbook

This guide describes the **unreleased next major** of `coisa/error-handler` for PHP 8.3–8.5. Every recipe below is a complete executable file in `examples/`. Run it from the repository after `composer install --no-plugins --no-scripts`. Load the matching checkout; the published 2.0 API is different. If copying a recipe into an application, adjust the Composer autoload path.

The checkout's development dependencies supply Nyholm PSR-7 and Phly EventDispatcher for these examples. Applications choose their own PSR implementations; these packages are not runtime dependencies of this library. To use the same implementations in an application, install `nyholm/psr7` for HTTP and `phly/phly-event-dispatcher` for events. The full FastForward framework is unnecessary.

## Built-in adapters

Names are relative to `CoiSA\ErrorHandler`; use the imports in each recipe.

| Adapter or contract | Responsibility | Recipe |
| --- | --- | --- |
| `ErrorHandler / ErrorHandlerInterface` | Process registration, callback restoration and direct delivery | [Example](#cli) |
| `CallableThrowableHandler / ThrowableHandlerInterface` | Application-owned reporting adapter | [Example](#reporters) |
| `ThrowableHandlerAggregate` | Ordered reporters; stop when a reporter throws | [Example](#reporters) |
| `ThrowErrorExceptionPhpErrorHandler / PhpErrorHandlerInterface` | Default severity-aware conversion and custom PHP policy | [Example](#custom-handlers) |
| `PhpLastErrorShutdownHandler / ShutdownHandlerInterface` | Default fatal inspection and application shutdown policy | [Example](#custom-handlers) |
| `CallableShutdownHandler` | Adapt a shutdown callback | [Example](#custom-handlers) |
| `DispatchThrowableHandler / DispatchErrorEventThrowableHandler` | Dispatch the original object or an ErrorEvent wrapper | [Example](#events) |
| `ErrorEvent / ErrorEventInterface` | Access getThrowable(); diagnostic __toString() for private sinks | [Example](#events) |
| `ThrowableCallableListener / ErrorEventCallableListener` | Adapt application event callbacks | [Example](#events) |
| `ThrowableListenerProvider / ErrorEventListenerProvider` | Select the supported event category | [Example](#events) |
| `LogErrorEventListener` | Send an ErrorEvent to an application PSR-3 logger | [Example](#events) |
| `ErrorHandlerServiceProvider / ThrowableHandlerAggregateFactory` | Lazy Interop definitions and optional reporter discovery | [Example](#fast-forward) |
| `ThrowableStreamFactory / ThrowableStreamFactoryInterface` | Capture deliberate output; generic fallback when empty | [Example](#http-rendering) |
| `ThrowableResponseFactory / ThrowableResponseFactoryInterface` | Map integer error codes 400–599; otherwise 500 | [Example](#http-rendering) |
| `ErrorHandlerMiddleware` | Scoped PSR-15 processing; return a PSR-7 response | [Example](#http-middleware) |
| `ErrorHandlerFactory / ErrorHandlerMiddlewareFactory` | Fresh handler and middleware lifetimes | [Example](#scopes) |

<a id="cli"></a>

## Process bootstrap

Keep one handler registered through process termination. Recoverable warnings report and continue; a successfully reported uncaught exception exits 1. This example deliberately exits 1 and writes two diagnostic lines to stderr. Do not wrap process bootstrap in a finally that unregisters before the uncaught callback runs.

Run `php examples/cli.php`. [Executable source](../examples/cli.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
});
$handler = new ErrorHandler($reporter);
$handler->register();

trigger_error('Example warning; execution continues.', E_USER_WARNING);
throw new RuntimeException('Example uncaught exception; exit status is 1.');
```

<a id="fast-forward"></a>

## Interop service provider

Implement ServiceProviderInterface with lazy getFactories() and getExtensions() maps. Merge the library first and the application last in one AggregateServiceProvider. FastForward resolves and caches services; registration remains an explicit application decision. This example prints Reported: FastForward provider warning and restores the earlier callbacks.

Run `php examples/fast-forward.php`. [Executable source](../examples/fast-forward.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$application = new class () implements ServiceProviderInterface {
    /**
     * Supplies application service definitions for the integration fixture.
     *
     * @return array<class-string, callable> Application service factories.
     */
    public function getFactories(): array
    {
        return [
            ThrowableHandlerInterface::class => static fn (): CallableThrowableHandler =>
                new CallableThrowableHandler(static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                }),
        ];
    }

    /**
     * Declares no application service extensions.
     *
     * @return array<string, callable> An empty extension map.
     */
    public function getExtensions(): array
    {
        return [];
    }
};

$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
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

CallableThrowableHandler adapts an application callback. ThrowableHandlerAggregate accepts constructor arguments and attach(), delivering the same Throwable in order. It stops on the first reporter failure. Direct handleThrowable() returns to the caller and needs no registration. Independent direct calls can resume after a reporter fails.

Run `php examples/reporters.php`. [Executable source](../examples/reporters.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;

require dirname(__DIR__) . '/vendor/autoload.php';

$deliveries = [];
$reporters = new ThrowableHandlerAggregate(
    new CallableThrowableHandler(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'first: ' . $error->getMessage();
    })
);
$reporters->attach(new CallableThrowableHandler(
    static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'second: ' . $error->getMessage();
    }
));

$errors = new ErrorHandler($reporters);
// Direct delivery needs no registration and does not terminate the process.
$errors->handleThrowable(new RuntimeException('Synthetic failure'));
echo implode(PHP_EOL, $deliveries) . PHP_EOL;
```

<a id="events"></a>

## PSR-14 dispatch, listeners and PSR-3 logging

The default provider aggregate discovers EventDispatcherInterface and attaches DispatchThrowableHandler plus DispatchErrorEventThrowableHandler. The first dispatches the original Throwable; the second wraps it in ErrorEvent. Subscribe intentionally to one or both paths: a listener on each means two application notifications. ThrowableListenerProvider selects Throwable objects; ErrorEventListenerProvider selects the built-in ErrorEvent. Callable listeners receive the selected object; LogErrorEventListener records its diagnostic string at the configured PSR-3 level. The example records two notifications and one private log entry.

Run `php examples/event-dispatcher.php`. [Executable source](../examples/event-dispatcher.php).

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
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$deliveries = [];
$logger = new class () extends AbstractLogger {
    /**
     * @var list<string> Synthetic private log records; replace with an application logger.
     */
    public array $records = [];

    /**
     * Records diagnostics without emitting them into an HTTP response.
     *
     * Untyped parameters also support applications using psr/log 1.x.
     *
     * @param mixed $level The PSR-3 log level.
     * @param mixed $message The diagnostic message.
     * @param array<string, mixed> $context The application log context.
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = $level . ': ' . (string) $message;
    }
};
$throwables = new ThrowableListenerProvider(
    new ThrowableCallableListener(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'throwable: ' . $error->getMessage();
    })
);
$events = new ErrorEventListenerProvider(
    new ErrorEventCallableListener(static function (ErrorEventInterface $event) use (&$deliveries): void {
        $deliveries[] = 'event: ' . $event->getThrowable()->getMessage();
    })
);
$events->attach(new LogErrorEventListener($logger, LogLevel::CRITICAL));
$dispatcher = new EventDispatcher(new ListenerProviderAggregate($throwables, $events));
$application = new ArrayServiceProvider([
    EventDispatcherInterface::class => static fn (): EventDispatcherInterface => $dispatcher,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
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

## PSR-15 HTTP middleware with the provider

Bind the application's PSR-17 response and stream factories and a private reporter. ErrorHandlerMiddleware returns successful downstream responses unchanged and maps a downstream Throwable to an error response. The example returns HTTP 503 with Internal Server Error, reports the failure privately, then reports a warning through the restored process handler. It prints Reported: 2. Your application owns response emission, headers and content type.

Run `php examples/http-middleware.php`. [Executable source](../examples/http-middleware.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = [];
$psr17 = new Psr17Factory();
$reporter = new CallableThrowableHandler(static function (Throwable $error) use (&$reported): void {
    $reported[] = $error; // Replace with a private logger; do not echo diagnostics.
});
$application = new ArrayServiceProvider([
    ThrowableHandlerInterface::class => static fn (): ThrowableHandlerInterface => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
$process = $services->get(ErrorHandlerInterface::class);
$middleware = $services->get(ErrorHandlerMiddleware::class);
$downstream = new class () implements RequestHandlerInterface {
    /**
     * Simulates an application failure with an HTTP error status.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return ResponseInterface A response supplied by the error middleware.
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

## Controlled JSON rendering without a container

Compose ThrowableStreamFactory and ThrowableResponseFactory directly. Separate private diagnostic side effects from deliberately public renderer output. This example emits only a fixed JSON error and adds application/json to the resulting response. Never echo exception messages, traces or credentials in a production renderer. To inject these factories into provider middleware, bind ThrowableResponseFactoryInterface explicitly in the application provider.

Run `php examples/http-rendering.php`. [Executable source](../examples/http-rendering.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/vendor/autoload.php';

$privateDiagnostics = [];
$reporter = new CallableThrowableHandler(
    static function (Throwable $error) use (&$privateDiagnostics): void {
        $privateDiagnostics[] = $error; // Replace with a private logger.
    }
);
$publicRenderer = new CallableThrowableHandler(static function (): void {
    echo json_encode(['error' => 'Service unavailable'], JSON_THROW_ON_ERROR);
});
$psr17 = new Psr17Factory();
$streams = new ThrowableStreamFactory($psr17, new ThrowableHandlerAggregate($reporter, $publicRenderer));
$responses = new ThrowableResponseFactory($streams, $psr17);
$response = $responses->createResponseFromThrowable(new RuntimeException('Private credentials', 503))
    ->withHeader('Content-Type', 'application/json');

printf("HTTP %d | %s\n", $response->getStatusCode(), $response->getHeaderLine('Content-Type'));
echo (string) $response->getBody() . PHP_EOL;
printf("Private reports: %d\n", count($privateDiagnostics));
```

<a id="custom-handlers"></a>

## Application PHP-error and shutdown handlers

ErrorHandler defaults to ThrowErrorExceptionPhpErrorHandler and PhpLastErrorShutdownHandler. The first honors error_reporting() and suppression, converts PHP errors and lets the outer handler report them; recoverable warnings continue. The second reports observable E_ERROR, E_PARSE, E_CORE_ERROR and E_COMPILE_ERROR at shutdown, without recovery. Override PhpErrorHandlerInterface to change conversion, or ShutdownHandlerInterface with CallableShutdownHandler for application notifications. This example simulates shutdown directly and unregisters before real termination: one PHP error and one shutdown notification.

Run `php examples/custom-handlers.php`. [Executable source](../examples/custom-handlers.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$phpErrors = new class () implements PhpErrorHandlerInterface {
    /**
     * @var list<string> Reportable PHP messages received by this application handler.
     */
    public array $messages = [];

    /**
     * Records reportable PHP errors without converting them to exceptions.
     *
     * @param int $code The PHP severity.
     * @param string $message The PHP message.
     * @param string $filename The source filename.
     * @param int $line The source line.
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
    ThrowableHandlerInterface::class => static fn (): ThrowableHandlerInterface =>
        new CallableThrowableHandler(static function (Throwable $error): void {
            error_log($error->getMessage());
        }),
    PhpErrorHandlerInterface::class => static fn (): PhpErrorHandlerInterface => $phpErrors,
    ShutdownHandlerInterface::class => static fn (): ShutdownHandlerInterface => $shutdown,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
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

FastForward caches get(ErrorHandlerInterface) and get(ErrorHandlerMiddleware). ErrorHandlerMiddlewareFactory invokes ErrorHandlerFactory to obtain a dedicated handler instead of sharing the cached process handler. Otherwise its finally could unregister the process handler. Resolve the callable factories and invoke each time you need a distinct nested scope. Plain autowiring does not provide that lifetime. This example nests two fresh middleware instances beneath a registered process handler and reports twice. PHP callbacks remain process-wide; this supports sequential nesting, not concurrent request or fiber isolation.

Run `php examples/scopes.php`. [Executable source](../examples/scopes.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = 0;
$psr17 = new Psr17Factory();
$reporter = new CallableThrowableHandler(static function () use (&$reported): void {
    ++$reported;
});
$application = new ArrayServiceProvider([
    ThrowableHandlerInterface::class => static fn (): ThrowableHandlerInterface => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
$makeHandler = $services->get(ErrorHandlerFactory::class);
$first = $makeHandler($services);
$second = $makeHandler($services);
$makeMiddleware = $services->get(ErrorHandlerMiddlewareFactory::class);
$outer = $makeMiddleware($services);
$inner = $makeMiddleware($services);
$downstream = new class () implements RequestHandlerInterface {
    /**
     * Throws a synthetic downstream failure for the inner scope.
     *
     * @param ServerRequestInterface $request The synthetic request.
     * @return ResponseInterface The response produced by the inner middleware.
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
     * @param MiddlewareInterface $inner The inner error middleware.
     * @param RequestHandlerInterface $downstream The application request handler.
     */
    public function __construct(private MiddlewareInterface $inner, private RequestHandlerInterface $downstream)
    {
    }

    /**
     * Enters the inner scope while the outer scope remains registered.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return ResponseInterface The inner response.
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
    $outer !== $inner ? 'yes' : 'no'
);
printf("HTTP %d | Reports: %d\n", $response->getStatusCode(), $reported);
```

## Failure and lifetime boundaries

Unregister in reverse registration order and use distinct handlers for nested scopes. A second register on the same active instance is a no-op. PHP retains one shutdown callback per instance until process exit; inactive callbacks do nothing. A fatal inside another shutdown callback can abort the remaining shutdown queue, even for a fresh handler; it is outside the reporting guarantee.

A reporter failure during a registration suppresses further delivery for that lifecycle. `ErrorHandler::hasReporterFailed()` exposes this state for integrations. Built-in middleware propagates that failure and restores callbacks instead of passing it to the renderer and retrying the reporter. A custom implementation of `ErrorHandlerInterface` owns its own failure policy. Independent unregistered direct calls do not retain the failed flag.

Reporter output captured by `ThrowableStreamFactory` becomes the public response body. Empty output becomes `Internal Server Error`; `0` remains a valid body. Renderers must balance removable buffers and never close caller buffers. A renderer or response-factory failure propagates after middleware cleanup; no HTTP response is emitted automatically.

Use explicit required reporter/renderer interface bindings and application-last merged definitions. FastForward can retry construction or fall back to autowiring concrete classes; do not infer successful delivery from `has()` or assume exception identity survives container resolution. See [provider details](fast-forward.md), [API contracts](api.md), [migration](migration.md) and [validation](testing.md).
