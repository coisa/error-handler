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

namespace CoiSA\ErrorHandler\Test\Functional\Container;

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
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
use Laminas\ServiceManager\ServiceManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerContainerTest.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
 *
 * @internal
 *
 * @coversNothing
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class ErrorHandlerContainerTest extends TestCase
{
    /** @var ServiceManager */
    private $serviceManager;

    /** @var ErrorHandlerContainer */
    private $container;

    /** @var EventDispatcher */
    private $eventDispatcher;

    /** @var ListenerProviderAggregate */
    private $listenerProvider;

    /**
     * Compose fresh application dependencies without installing PHP callbacks.
     */
    protected function setUp(): void
    {
        $this->serviceManager = new ServiceManager();
        $this->container      = new ErrorHandlerContainer($this->serviceManager);

        $this->listenerProvider = new ListenerProviderAggregate();
        $this->eventDispatcher  = new EventDispatcher($this->listenerProvider);

        $factory = new Psr17Factory();

        $this->serviceManager->setService(ResponseFactoryInterface::class, $factory);
        $this->serviceManager->setService(StreamFactoryInterface::class, $factory);
    }

    /**
     * Verify the library container advertises each factory-backed service.
     *
     * @param class-string $service the configured service identifier
     *
     * @dataProvider provideServiceProviderClassNames
     */
    public function testContainerHasConfigProviderFactories(string $service): void
    {
        self::assertTrue($this->container->has($service));
    }

    /**
     * Verify each factory-backed service resolves to its declared type.
     *
     * @param class-string $service the configured service identifier
     *
     * @dataProvider provideServiceProviderClassNames
     */
    public function testContainerProvideConfigProviderFactories(string $service): void
    {
        $this->serviceManager->setService(EventDispatcherInterface::class, $this->eventDispatcher);
        self::assertInstanceOf($service, $this->container->get($service));
    }

    /**
     * List the service identifiers backed by the provider's factory map.
     *
     * @return list<non-empty-array<int, class-string>> independent configured service identifiers
     */
    public static function provideServiceProviderClassNames(): iterable
    {
        $factories = (new ConfigProvider())->getFactories();

        return array_chunk(array_keys($factories), 1);
    }

    /**
     * Verify the default empty reporter aggregate accepts a throwable.
     */
    public function testErrorHandlerWithoutHandlerWillDoNothing(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $this->expectNotToPerformAssertions();
        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verify an application-supplied reporter receives the original throwable.
     */
    public function testCallableHandlerWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            function ($throwable) use ($exception): void {
                $this->assertSame($exception, $throwable);
            },
        );
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verify a reporter discovered by the default aggregate receives the throwable.
     */
    public function testCallableHandlerFromAggregationWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            function ($throwable) use ($exception): void {
                $this->assertSame($exception, $throwable);
            },
        );
        $this->serviceManager->setService(CallableThrowableHandler::class, $callableThrowableHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verify both event adapters and logging listeners receive the reported error.
     */
    public function testEventDispatcherCallableHandlerWillHandleThrowable(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorEventCallableListener = new ErrorEventCallableListener(
            /**
             * Assert the dispatched error event preserves the fixture throwable.
             *
             * @param ErrorEvent $event the dispatched error event
             */
            function ($event) use ($exception): void {
                $this->assertInstanceOf(ErrorEvent::class, $event);
                $this->assertSame($exception, $event->getThrowable());
                $this->assertSame((string) $exception, (string) $event);
            },
        );

        $throwableListener = new ThrowableCallableListener(
            /**
             * Assert the throwable event preserves the fixture object.
             *
             * @param \Throwable $throwable the dispatched throwable
             */
            function (\Throwable $throwable) use ($exception): void {
                $this->assertSame($exception, $throwable);
            },
        );

        $logErrorEventListener = new LogErrorEventListener(
            new AssertThrowableTestCaseLogger($this, $exception),
        );

        $errorEventListenerProvider = new ErrorEventListenerProvider($errorEventCallableListener);
        $errorEventListenerProvider->attach($logErrorEventListener);

        $throwableListenerProvider = new ThrowableListenerProvider($throwableListener);
        $throwableListenerProvider->attach($throwableListener);

        $this->listenerProvider->attach($errorEventListenerProvider);
        $this->listenerProvider->attach($throwableListenerProvider);

        $this->serviceManager->setService(EventDispatcherInterface::class, $this->eventDispatcher);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verify suppressed PHP error levels do not invoke the reporter.
     */
    public function testErrorHandlerRespectErrorReporting(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            function ($throwable): void {
                $this->assertTrue(false);
            },
        );
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $oldLevel = error_reporting(E_ALL ^ E_USER_NOTICE);
        trigger_error('Test error reporting', E_USER_NOTICE);
        error_reporting($oldLevel);

        self::assertTrue(true);
    }

    /**
     * Verify a reported PHP error is converted into the library's error exception.
     */
    public function testThrowErrorExceptionPhpErrorHandlerThrowErrorException(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            function ($throwable): void {
                $this->assertInstanceOf(ErrorException::class, $throwable);
            },
        );
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        trigger_error('Test user error', E_USER_ERROR);
    }

    /**
     * Verify middleware reports a request failure and builds the expected response.
     */
    public function testErrorHandlerMiddlewareHandleRequestException(): void
    {
        $exception = new \InvalidArgumentException(uniqid('test', true), random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            static function (\Throwable $throwable): void {
                echo json_encode([
                    'code'    => $throwable->getCode(),
                    'message' => $throwable->getMessage(),
                ]);
            },
        );
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $serverRequest  = (new Psr17Factory())->createServerRequest('GET', '/');
        $requestHandler = new class ($exception) implements RequestHandlerInterface {
            /** @var \Throwable The failure raised by this request fixture. */
            private $exception;

            /**
             * Retain the request failure without throwing during construction.
             *
             * @param \Throwable $exception the throwable raised when handling a request
             */
            public function __construct(\Throwable $exception)
            {
                $this->exception = $exception;
            }

            /**
             * Raise the configured fixture failure for the supplied request.
             *
             * @param ServerRequestInterface $serverRequest the synthetic request
             *
             * @return ResponseInterface a response is never produced by this fixture
             *
             * @throws \Throwable the failure supplied at construction
             */
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                throw $this->exception;
            }
        };

        /** @var ErrorHandlerMiddleware $middleware The known middleware service from the fixture map. */
        $middleware = $this->container->get(ErrorHandlerMiddleware::class);
        $response   = $middleware->process($serverRequest, $requestHandler);

        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertSame($exception->getCode(), $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        self::assertIsArray($body);
        self::assertSame($exception->getCode(), $body['code']);
        self::assertSame($exception->getMessage(), $body['message']);
    }

    /**
     * Verify shutdown handling is inert while the process handler is unregistered.
     */
    public function testUnregisteredErrorHandlerHandleShutdownWillDoNothing(): void
    {
        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->handleShutdown();

        $output = $this->getActualOutput();
        self::assertEmpty($output);
    }

    /**
     * Verify a shutdown failure is reported through the configured throwable handler.
     */
    public function testErrorHandlerHandleShutdownWithErrorWillHandledByThrowableHandler(): void
    {
        $exception = new \InvalidArgumentException(uniqid('test', true), random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(
            /**
             * Receive the throwable reported by this behavior fixture.
             *
             * @param \Throwable $throwable the throwable supplied to the reporter
             */
            function ($throwable) use ($exception): void {
                $this->assertSame($exception, $throwable);
            },
        );

        $callableShutdownHandler = new CallableShutdownHandler(
            /**
             * Raise the configured shutdown failure for the reporting assertion.
             *
             * @throws \InvalidArgumentException the shutdown fixture throwable
             */
            static function () use ($exception): void {
                throw $exception;
            },
        );

        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);
        $this->serviceManager->setService(ShutdownHandlerInterface::class, $callableShutdownHandler);

        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleShutdown();
    }

    /**
     * Verify unregistering an inactive process handler returns normally.
     */
    public function testUnregisterNotRegisteredErrorHandlerShouldNotThrowExceptions(): void
    {
        /** @var ErrorHandler $errorHandler The known process-handler service from the fixture map. */
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->unregister();
        self::assertTrue(true);
    }
}
