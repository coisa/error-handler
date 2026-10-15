# Usage cookbook

This guide describes the **unreleased next major** of `coisa/error-handler` for PHP 8.3–8.5. Every recipe below is a complete executable file. Run it after `composer install --no-plugins --no-scripts`. Applications resolve their own Composer locks. If copying a recipe, adjust its autoload path. Published 2.0 has different behavior.

The checkout supplies Nyholm PSR-7 and Phly EventDispatcher as development dependencies. Applications choose their own PSR implementations; use `composer require nyholm/psr7 phly/phly-event-dispatcher` for the same HTTP/event examples. No full framework is required.

**Reporting and rendering are separate.** Reporters return void and own private diagnostics. Middleware reports once, then calls a pure response factory. JSON can be returned as a stream without echo or buffering. The optional compatibility capture adapter is demonstrated separately; it converts deliberately public output to a body.

## Built-in adapters

Names below are relative to `CoiSA\ErrorHandler`; use each example's imports.

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
| `GenericThrowableStreamFactory` | Default public stream; no reporting or output buffer | [Example](#http-middleware) |
| `ThrowableStreamFactory / ThrowableStreamFactoryInterface` | Optional capture adapter for deliberately public templates | [Example](#output-capture) |
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

Implement ServiceProviderInterface with lazy getFactories() and getExtensions() maps. Pass the library provider class and the application provider directly to container(). This example supplies an optional CallableThrowableHandler that the library aggregate discovers. FastForward resolves and caches services; registration remains an explicit application decision. It prints Reported: FastForward provider warning and restores the earlier callbacks.

Run `php examples/fast-forward.php`. [Executable source](../examples/fast-forward.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
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
            CallableThrowableHandler::class => static fn (): CallableThrowableHandler =>
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

## PSR-15 HTTP middleware with the provider

Bind application PSR-17 factories and a private diagnostic reporter. The middleware reports a downstream Throwable once, then asks a separate factory for a response. GenericThrowableStreamFactory is the provider default: it returns Internal Server Error without invoking a reporter or capturing output. Reporter output is not the response body; HTTP reporters must keep diagnostics in private sinks. The example returns 503 and then reports a warning through the restored process handler, for two private reports. Applications own emission and headers.

Run `php examples/http-middleware.php`. [Executable source](../examples/http-middleware.php).

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

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = [];

$psr17 = new Psr17Factory();

$reporter = new CallableThrowableHandler(static function (Throwable $error) use (&$reported): void {
    $reported[] = $error; // Replace with a private logger; do not echo diagnostics.
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn (): CallableThrowableHandler => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

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

## Explicit JSON streams without echo or buffering

Keep the reporter's void contract for private diagnostics. Implement ThrowableStreamFactoryInterface to return a JSON StreamInterface directly, and compose it with ThrowableResponseFactory. Middleware reports once before rendering. This complete example adds application/json and exposes only a fixed public message; private throwable details stay in the reporter. A ready JSON response adapter is tracked separately in issue #7.

Run `php examples/http-rendering.php`. [Executable source](../examples/http-rendering.php).

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

require dirname(__DIR__) . '/vendor/autoload.php';

$privateDiagnostics = [];
$reporter = new CallableThrowableHandler(
    static function (Throwable $error) use (&$privateDiagnostics): void {
        $privateDiagnostics[] = $error; // Replace with a private logger; emit no response body.
    }
);

$psr17 = new Psr17Factory();
$streams = new class ($psr17) implements ThrowableStreamFactoryInterface {
    /**
     * Stores the application's PSR-17 factory.
     *
     * @param StreamFactoryInterface $streams Creates public response body streams.
     */
    public function __construct(private StreamFactoryInterface $streams)
    {
    }

    /**
     * Returns controlled JSON directly, independently of diagnostic reporting.
     *
     * @param Throwable $throwable The private failure whose details stay undisclosed.
     *
     * @return StreamInterface A public JSON body without echo or output buffering.
     */
    public function createStreamFromThrowable(Throwable $throwable): StreamInterface
    {
        return $this->streams->createStream(
            json_encode(['error' => 'Service unavailable'], JSON_THROW_ON_ERROR)
        );
    }
};

$responses = new ThrowableResponseFactory($streams, $psr17);
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $responses);

$downstream = new class () implements RequestHandlerInterface {
    /**
     * Simulates a private application failure with an HTTP error code.
     *
     * @param ServerRequestInterface $request The incoming request.
     *
     * @return ResponseInterface The error response produced by middleware.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Private credentials', 503);
    }
};

$response = $middleware->process($psr17->createServerRequest('GET', '/'), $downstream)
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
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
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
    CallableThrowableHandler::class => static fn (): CallableThrowableHandler =>
        new CallableThrowableHandler(static function (Throwable $error): void {
            error_log($error->getMessage());
        }),
    PhpErrorHandlerInterface::class => static fn (): PhpErrorHandlerInterface => $phpErrors,
    ShutdownHandlerInterface::class => static fn (): ShutdownHandlerInterface => $shutdown,
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

FastForward caches get(ErrorHandlerInterface) and get(ErrorHandlerMiddleware). ErrorHandlerMiddlewareFactory invokes ErrorHandlerFactory to obtain a dedicated handler instead of sharing the cached process handler. Otherwise its finally could unregister the process handler. Resolve the callable factories and invoke each time you need a distinct nested scope. Plain autowiring does not provide that lifetime. This example nests two fresh middleware instances beneath a registered process handler and reports twice. PHP callbacks remain process-wide; this supports sequential nesting, not concurrent request or fiber isolation.

Run `php examples/scopes.php`. [Executable source](../examples/scopes.php).

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

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = 0;

$psr17 = new Psr17Factory();

$reporter = new CallableThrowableHandler(static function () use (&$reported): void {
    ++$reported;
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn (): CallableThrowableHandler => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

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

<a id="output-capture"></a>

## Optional output-capture compatibility adapter

ThrowableStreamFactory remains available for deliberately public echo-based templates. In this adapter alone, output from its injected callable becomes the response stream; a JSON template would have to echo JSON. New JSON renderers can return a stream directly as above. The provider does not select this adapter by default. Middleware already reports diagnostics: pass a separate public renderer here, never the same diagnostic reporter again. Empty captured output becomes Internal Server Error; 0 remains a valid body.

Run `php examples/output-capture.php`. [Executable source](../examples/output-capture.php).

```php
<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/vendor/autoload.php';

// Optional compatibility adapter: only deliberately public output belongs here.
// Middleware now reports privately before rendering; do not inject its reporter again.
$publicRenderer = new CallableThrowableHandler(static function (): void {
    echo 'Public failure page';
});

$streams = new ThrowableStreamFactory(new Psr17Factory(), $publicRenderer);

$body = $streams->createStreamFromThrowable(new RuntimeException('Private diagnostics'));

printf("Captured body: %s\n", (string) $body);
```

## Failure and lifetime boundaries

Unregister in reverse order and use distinct handlers for nested scopes. Registering an active instance again is a no-op. PHP retains one shutdown callback per instance; inactive callbacks do nothing. A fatal inside another shutdown callback can abort the remaining queue, even with a fresh handler.

Middleware accepts a handler implementing both ErrorHandlerInterface and ThrowableHandlerInterface. The same instance reports PHP errors and downstream throwables, preserving its recursion/failure guard. A reporter failure propagates after callback cleanup and is not re-rendered through that reporter. A renderer failure also propagates after cleanup. Custom implementations own their failure policy. Independent unregistered direct deliveries do not retain a failed flag.

The default GenericThrowableStreamFactory returns a controlled body and does not inspect reporter output. Reporters used in HTTP must not echo diagnostics: unsolicited output is outside the response contract. The optional ThrowableStreamFactory still captures its injected public renderer's output. Keep it separate from the diagnostic reporter already called by middleware. Its renderers must balance removable buffers and never close caller buffers; renderer failures retain precedence during cleanup.

Use explicit required reporter/renderer interface bindings and application-last merged definitions. FastForward can retry construction and fall back to autowiring concrete classes. See [provider details](fast-forward.md), [API](api.md), [migration](migration.md) and [validation](testing.md). The ready JSON factory proposal is [issue #7](https://github.com/coisa/error-handler/issues/7).
