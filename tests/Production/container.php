<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

use function FastForward\Container\container;

require $argv[1] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';

/** Keep observable reporter state across indirect calls through container services. */
$reports = new class {
    /** @var list<Throwable> Original events in the order they were reported. */
    private array $events = [];

    /**
     * Record the exact event passed to the configured application reporter.
     *
     * @param Throwable $throwable the original synthetic event
     */
    public function record(Throwable $throwable): void
    {
        $this->events[] = $throwable;
    }

    /** @return bool whether any event has reached the application reporter */
    public function hasReports(): bool
    {
        return [] !== $this->events;
    }

    /** @return list<Throwable> the complete ordered observations */
    public function all(): array
    {
        return $this->events;
    }
};

$reporter = new CallableThrowableHandler(
    /**
     * Record synthetic reports without a logger, transport or diagnostic output.
     *
     * @param Throwable $throwable the original diagnostic supplied to each core handler
     *
     * @return void
     */
    static function (Throwable $throwable) use ($reports): void {
        $reports->record($throwable);
    },
);

/** Application-owned PSR-17 sentinel that must remain unused during lookup. */
$messages = new class implements ResponseFactoryInterface, StreamFactoryInterface {
    /**
     * Reject rendering during a construction-only provider lookup.
     *
     * @param int    $code         the unused response status
     * @param string $reasonPhrase the unused public reason phrase
     *
     * @return ResponseInterface no response is constructed by this sentinel
     *
     * @throws LogicException if service lookup unexpectedly renders a response
     */
    public function createResponse(int $code = 200, string $reasonPhrase = ''): ResponseInterface
    {
        throw new LogicException('Provider lookup must not render a response.');
    }

    /**
     * Reject stream creation during a construction-only provider lookup.
     *
     * @param string $content the unused public stream content
     *
     * @return StreamInterface no stream is constructed by this sentinel
     *
     * @throws LogicException if service lookup unexpectedly renders content
     */
    public function createStream(string $content = ''): StreamInterface
    {
        throw new LogicException('Provider lookup must not create a stream.');
    }

    /**
     * Reject filesystem access during provider construction.
     *
     * @param string $filename the unused synthetic filename
     * @param string $mode     the unused file mode
     *
     * @return StreamInterface no file is opened by this sentinel
     *
     * @throws LogicException if service lookup unexpectedly opens a stream
     */
    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        throw new LogicException('Provider lookup must not open a file.');
    }

    /**
     * Reject resource access during provider construction.
     *
     * @param resource $resource the unused caller-owned stream resource
     *
     * @return StreamInterface no resource is consumed by this sentinel
     *
     * @throws LogicException if service lookup unexpectedly consumes a resource
     */
    public function createStreamFromResource($resource): StreamInterface
    {
        throw new LogicException('Provider lookup must not consume a resource.');
    }
};

$application = new ArrayServiceProvider([
    // @return CallableThrowableHandler the application's explicitly configured reporter
    CallableThrowableHandler::class => static fn(): CallableThrowableHandler => $reporter,
    // @return StreamFactoryInterface the construction-only application stream dependency
    StreamFactoryInterface::class => static fn(): StreamFactoryInterface => $messages,
    // @return ResponseFactoryInterface the construction-only application response dependency
    ResponseFactoryInterface::class => static fn(): ResponseFactoryInterface => $messages,
]);

/** @return bool confirms that the fixture owns the surrounding PHP error callback */
$previousError = static fn(): bool => true;

/**
 * Fail if unexpected uncaught delivery reaches the surrounding fixture callback.
 *
 * @param Throwable $throwable the unexpected native failure
 *
 * @return void
 *
 * @throws LogicException with that failure as its cause
 */
$previousException = static function (Throwable $throwable): void {
    throw new LogicException('Unexpected exception delivery during provider construction.', 0, $throwable);
};

set_error_handler($previousError);
set_exception_handler($previousException);

try {
    $publicClasses = [
        JsonThrowableResponseFactory::class,
        ReportingThrowableResponseFactory::class,
        WhoopsThrowableResponseFactory::class,
        JsonErrorHandlerServiceProvider::class,
        WhoopsErrorHandlerServiceProvider::class,
    ];

    foreach ($publicClasses as $class) {
        if (!class_exists($class)) {
            throw new RuntimeException('The production autoloader must expose ' . $class);
        }
    }

    // Declaring optional profile maps must not construct a Whoops page or register callbacks.
    (new JsonErrorHandlerServiceProvider())->getFactories();
    (new WhoopsErrorHandlerServiceProvider())->getFactories();

    $services = container(ErrorHandlerServiceProvider::class, $application);
    $handler = $services->get(ErrorHandlerInterface::class);
    $aggregate = $services->get(ThrowableHandlerInterface::class);
    $streams = $services->get(ThrowableStreamFactoryInterface::class);
    $responses = $services->get(ThrowableResponseFactoryInterface::class);

    if (
        !$handler instanceof ErrorHandler || !$aggregate instanceof ThrowableHandlerAggregate
        || !$streams instanceof GenericThrowableStreamFactory || !$responses instanceof ThrowableResponseFactory
    ) {
        throw new RuntimeException('The Interop provider must resolve its actual default classes.');
    }

    if (
        $services->get(ErrorHandler::class) !== $handler
        || $services->get(ThrowableHandlerAggregate::class) !== $aggregate
        || $services->get(GenericThrowableStreamFactory::class) !== $streams
        || $services->get(ThrowableResponseFactory::class) !== $responses
    ) {
        throw new RuntimeException('Provider aliases must preserve the cached concrete service identities.');
    }

    $handlerFactory = $services->get(ErrorHandlerFactory::class);
    $middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);
    if (
        !$handlerFactory instanceof ErrorHandlerFactory
        || !$middlewareFactory instanceof ErrorHandlerMiddlewareFactory
    ) {
        throw new RuntimeException('Fresh-scope factory services must be available in the production graph.');
    }

    $first = $handlerFactory($services);
    $second = $handlerFactory($services);
    if ($first === $handler || $second === $handler || $first === $second) {
        throw new RuntimeException('Handler factories must produce scopes distinct from the cached process handler.');
    }

    $cachedMiddleware = $services->get(ErrorHandlerMiddleware::class);
    if (
        !$cachedMiddleware instanceof ErrorHandlerMiddleware
        || $services->get(ErrorHandlerMiddleware::class) !== $cachedMiddleware
        || $middlewareFactory($services) === $middlewareFactory($services)
    ) {
        throw new RuntimeException('Middleware lookup must cache while explicit factories return fresh wrappers.');
    }

    $activeError = set_error_handler($previousError);
    restore_error_handler();
    $activeException = set_exception_handler($previousException);
    restore_exception_handler();
    if ($activeError !== $previousError || $activeException !== $previousException || $reports->hasReports()) {
        throw new RuntimeException('Service resolution must not register callbacks or invoke reporters.');
    }

    $failure = new RuntimeException('Synthetic production-provider diagnostic.');
    $handler->handleThrowable($failure);
    $first->handleThrowable($failure);
    $second->handleThrowable($failure);
    if ([$failure, $failure, $failure] !== $reports->all()) {
        throw new RuntimeException('Cached and explicit fresh handlers must report the identical event once each.');
    }
} finally {
    restore_error_handler();
    restore_exception_handler();
}

echo 'Production Interop provider passed on PHP ' . PHP_VERSION . PHP_EOL;
