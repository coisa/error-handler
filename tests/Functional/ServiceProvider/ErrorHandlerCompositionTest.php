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

namespace CoiSA\ErrorHandler\Test\Functional\ServiceProvider;

use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\Test\Log\AssertThrowableTestCaseLogger;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

/**
 * Verifies reporter, event and HTTP composition through the Interop service provider.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\ServiceProvider
 *
 * @internal
 */
#[CoversNothing]
final class ErrorHandlerCompositionTest extends TestCase
{
    /**
     * @var array<class-string, object> application services supplied before resolving the container
     */
    private array $services = [];

    /**
     * @var null|ContainerInterface lazily composed container for the current test
     */
    private ?ContainerInterface $container = null;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    /**
     * @var ListenerProviderAggregate
     */
    private $listenerProvider;

    /**
     * Creates isolated container and reporting collaborators for each test.
     */
    protected function setUp(): void
    {
        $this->listenerProvider = new ListenerProviderAggregate();
        $this->eventDispatcher  = new EventDispatcher($this->listenerProvider);

        $factory = new Psr17Factory();

        $this->services = [ResponseFactoryInterface::class => $factory, StreamFactoryInterface::class => $factory];
    }

    /**
     * Restores native PHP callbacks after each integration test.
     */
    protected function tearDown(): void
    {
        if (null !== $this->container) {
            $this->errorHandler()->unregister();
        }
    }

    /**
     * Verifies the configured container advertises every library service.
     *
     * @param class-string $service the service identifier under test
     */
    #[DataProvider('provideServiceProviderClassNames')]
    public function testContainerHasProviderServices(string $service): void
    {
        self::assertTrue($this->container()->has($service));
    }

    /**
     * Verifies service construction with application HTTP and event dependencies.
     *
     * @param class-string $service the service identifier under test
     */
    #[DataProvider('provideServiceProviderClassNames')]
    public function testContainerResolvesProviderServices(string $service): void
    {
        $this->services[EventDispatcherInterface::class] = $this->eventDispatcher;
        self::assertInstanceOf($service, $this->container()->get($service));
    }

    /**
     * Provides service identifiers explicitly declared by the Interop provider.
     *
     * @return list<array{class-string}> declared service-identifier cases
     */
    public static function provideServiceProviderClassNames(): iterable
    {
        $factories = (new ErrorHandlerServiceProvider())->getFactories();

        return array_chunk(array_keys($factories), 1);
    }

    /**
     * Composes the library provider with application service definitions last.
     *
     * @return ContainerInterface the shared container for the current test
     */
    private function container(): ContainerInterface
    {
        $application = new ArrayServiceProvider(array_map(
            static fn(object $service): \Closure => static fn(): object => $service,
            $this->services,
        ));

        return $this->container ??= container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            $application,
        ));
    }

    /**
     * Resolves and verifies the concrete process handler supplied by the provider.
     *
     * @return ErrorHandler the real container-resolved process handler
     */
    private function errorHandler(): ErrorHandler
    {
        $handler = $this->container()->get(ErrorHandler::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);

        return $handler;
    }

    /**
     * Verifies an empty aggregate intentionally produces no diagnostics.
     */
    public function testErrorHandlerWithoutHandlerWillDoNothing(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $this->expectOutputString('');
        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verifies an explicit reporter receives the original throwable.
     */
    public function testCallableHandlerWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verifies optional callable reporting through the default aggregate.
     */
    public function testCallableHandlerFromAggregationWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });
        $this->services[CallableThrowableHandler::class] = $callableThrowableHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verifies throwable and error-event listeners receive the original diagnostics.
     */
    public function testEventDispatcherCallableHandlerWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorEventCallableListener = new ErrorEventCallableListener(function ($event) use ($exception): void {
            $this->assertInstanceOf(ErrorEvent::class, $event);
            $this->assertSame($exception, $event->getThrowable());
            $this->assertSame((string) $exception, (string) $event);
        });

        $throwableListener = new ThrowableCallableListener(function (\Throwable $throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });

        $logErrorEventListener = new LogErrorEventListener(
            new AssertThrowableTestCaseLogger($this, $exception),
        );

        $errorEventListenerProvider = new ErrorEventListenerProvider($errorEventCallableListener);
        $errorEventListenerProvider->attach($logErrorEventListener);

        $throwableListenerProvider = new ThrowableListenerProvider($throwableListener);
        $throwableListenerProvider->attach($throwableListener);

        $this->listenerProvider->attach($errorEventListenerProvider);
        $this->listenerProvider->attach($throwableListenerProvider);

        $this->services[EventDispatcherInterface::class] = $this->eventDispatcher;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verifies masked PHP notices do not reach the reporter.
     */
    #[WithoutErrorHandler]
    public function testErrorHandlerRespectErrorReporting(): void
    {
        $received = [];
        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Records any delivery that would violate the masked-notice policy.
             *
             * @param \Throwable $throwable the converted event, if incorrectly delivered
             */
            static function (\Throwable $throwable) use (&$received): void {
                $received[] = $throwable;
            },
        );
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $oldLevel = error_reporting(E_ALL ^ E_USER_NOTICE);

        try {
            trigger_error('Test error reporting', E_USER_NOTICE);
        } finally {
            error_reporting($oldLevel);
        }

        self::assertSame([], $received);
    }

    /**
     * Verifies reportable PHP warnings are converted to ErrorException.
     */
    #[WithoutErrorHandler]
    public function testThrowErrorExceptionPhpErrorHandlerThrowErrorException(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable): void {
            $this->assertInstanceOf(ErrorException::class, $throwable);
        });
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        trigger_error('Test user warning', E_USER_WARNING);
    }

    /**
     * Verifies middleware captures downstream exceptions and renders an application response.
     */
    public function testErrorHandlerMiddlewareHandleRequestException(): void
    {
        $exception = new \InvalidArgumentException(uniqid('test', true), random_int(400, 500));

        $received = [];
        $callableThrowableHandler = new CallableThrowableHandler(
            static function (\Throwable $throwable) use (&$received): void {
                $received[] = $throwable;
            },
        );
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $serverRequest  = (new Psr17Factory())->createServerRequest('GET', '/');
        $requestHandler = new class ($exception) implements RequestHandlerInterface {
            /**
             * @var \Throwable the downstream failure to propagate
             */
            private $exception;

            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $exception the downstream throwable
             */
            public function __construct(\Throwable $exception)
            {
                $this->exception = $exception;
            }

            /**
             * Executes the synthetic downstream behavior for the requested test route.
             *
             * @param ServerRequestInterface $serverRequest the synthetic incoming request
             *
             * @return ResponseInterface the fixture result
             */
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $middleware = $this->container()->get(ErrorHandlerMiddleware::class);
        self::assertInstanceOf(ErrorHandlerMiddleware::class, $middleware);
        $response   = $middleware->process($serverRequest, $requestHandler);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame($exception->getCode(), $response->getStatusCode());

        self::assertSame('Internal Server Error', (string) $response->getBody());
        self::assertSame([$exception], $received);
    }

    /**
     * Verifies an inactive handler does not process shutdown notifications.
     */
    public function testUnregisteredErrorHandlerHandleShutdownWillDoNothing(): void
    {
        $this->expectOutputString('');
        $errorHandler = $this->errorHandler();
        $errorHandler->handleShutdown();
    }

    /**
     * Verifies a custom shutdown failure reaches the configured reporter.
     */
    public function testErrorHandlerHandleShutdownWithErrorWillHandledByThrowableHandler(): void
    {
        $exception = new \InvalidArgumentException(uniqid('test', true), random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });

        $callableShutdownHandler = new CallableShutdownHandler(static function () use ($exception): void {
            throw $exception;
        });

        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;
        $this->services[ShutdownHandlerInterface::class] = $callableShutdownHandler;

        $errorHandler = $this->errorHandler();
        $errorHandler->register();

        $errorHandler->handleShutdown();
    }

    /**
     * Verifies unregistering an inactive handler is idempotent.
     */
    public function testUnregisterNotRegisteredErrorHandlerShouldNotThrowExceptions(): void
    {
        $errorHandler = $this->errorHandler();
        $before = self::callbacks();
        $errorHandler->unregister();
        self::assertSame($before, self::callbacks());
    }

    /**
     * Snapshots both callbacks without retaining a probe on either native stack.
     *
     * @return array{null|callable, null|callable} current PHP error and exception callbacks
     */
    private static function callbacks(): array
    {
        /** @return bool A temporary callback that observes the prior error callback. */
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /**
             * Supplies a temporary exception callback without handling a real failure.
             *
             * @param \Throwable $error the unused probe input
             */
            static function (\Throwable $error): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
