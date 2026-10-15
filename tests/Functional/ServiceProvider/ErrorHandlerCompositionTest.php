<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

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
 * @coversNothing
 */
final class ErrorHandlerCompositionTest extends TestCase
{
    /**
     * @var array<class-string, object> Application services supplied before resolving the container.
     */
    private array $services = [];

    /**
     * @var ContainerInterface|null Lazily composed container for the current test.
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
        if ($this->container !== null) {
            $this->container->get(ErrorHandler::class)->unregister();
        }
    }

    /**
     * Provides service identifiers explicitly declared by the Interop provider.
     *
     * @return list<array{class-string}> Declared service-identifier cases.
     */
    public static function provideServiceProviderClassNames(): array
    {
        $factories = (new ErrorHandlerServiceProvider())->getFactories();

        return array_chunk(array_keys($factories), 1);
    }

    /**
     * Verifies the configured container advertises every library service.
     *
     * @param class-string $service The service identifier under test.
     */
    #[DataProvider('provideServiceProviderClassNames')]
    public function testContainerHasProviderServices(string $service): void
    {
        static::assertTrue($this->container()->has($service));
    }

    /**
     * Verifies service construction with application HTTP and event dependencies.
     *
     * @param class-string $service The service identifier under test.
     */
    #[DataProvider('provideServiceProviderClassNames')]
    public function testContainerResolvesProviderServices(string $service): void
    {
        $this->services[EventDispatcherInterface::class] = $this->eventDispatcher;
        static::assertInstanceOf($service, $this->container()->get($service));
    }

    /**
     * Composes the library provider with application service definitions last.
     *
     * @return ContainerInterface The shared container for the current test.
     */
    private function container(): ContainerInterface
    {
        $application = new ArrayServiceProvider(array_map(
            static fn (object $service): \Closure => static fn (): object => $service,
            $this->services
        ));

        return $this->container ??= container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            $application
        ));
    }

    /**
     * Verifies an empty aggregate intentionally produces no diagnostics.
     */
    public function testErrorHandlerWithoutHandlerWillDoNothing(): void
    {
        $message   = uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->register();

        $this->expectNotToPerformAssertions();
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

        $errorHandler = $this->container()->get(ErrorHandler::class);
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

        $errorHandler = $this->container()->get(ErrorHandler::class);
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
            new AssertThrowableTestCaseLogger($this, $exception)
        );

        $errorEventListenerProvider = new ErrorEventListenerProvider($errorEventCallableListener);
        $errorEventListenerProvider->attach($logErrorEventListener);

        $throwableListenerProvider = new ThrowableListenerProvider($throwableListener);
        $throwableListenerProvider->attach($throwableListener);

        $this->listenerProvider->attach($errorEventListenerProvider);
        $this->listenerProvider->attach($throwableListenerProvider);

        $this->services[EventDispatcherInterface::class] = $this->eventDispatcher;

        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    /**
     * Verifies masked PHP notices do not reach the reporter.
     */
    public function testErrorHandlerRespectErrorReporting(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable): void {
            $this->assertTrue(false);
        });
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->register();

        $oldLevel = error_reporting(E_ALL ^ E_USER_NOTICE);
        trigger_error('Test error reporting', E_USER_NOTICE);
        error_reporting($oldLevel);

        static::assertTrue(true);
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

        $errorHandler = $this->container()->get(ErrorHandler::class);
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
            }
        );
        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;

        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->register();

        $serverRequest  = (new Psr17Factory())->createServerRequest('GET', '/');
        $requestHandler = new class ($exception) implements RequestHandlerInterface {
            /**
             * @var \Throwable The downstream failure to propagate.
             */
            private $exception;

            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $exception The downstream throwable.
             */
            public function __construct(\Throwable $exception)
            {
                $this->exception = $exception;
            }

            /**
             * Executes the synthetic downstream behavior for the requested test route.
             *
             * @param ServerRequestInterface $serverRequest The synthetic incoming request.
             *
             * @return ResponseInterface The fixture result.
             */
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $middleware = $this->container()->get(ErrorHandlerMiddleware::class);
        $response   = $middleware->process($serverRequest, $requestHandler);

        static::assertInstanceOf(ResponseInterface::class, $response);
        static::assertSame($exception->getCode(), $response->getStatusCode());

        static::assertSame('Internal Server Error', (string) $response->getBody());
        static::assertSame([$exception], $received);
    }

    /**
     * Verifies an inactive handler does not process shutdown notifications.
     */
    public function testUnregisteredErrorHandlerHandleShutdownWillDoNothing(): void
    {
        $this->expectOutputString('');
        $errorHandler = $this->container()->get(ErrorHandler::class);
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

        $callableShutdownHandler = new CallableShutdownHandler(function () use ($exception): void {
            throw $exception;
        });

        $this->services[ThrowableHandlerInterface::class] = $callableThrowableHandler;
        $this->services[ShutdownHandlerInterface::class] = $callableShutdownHandler;

        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleShutdown();
    }

    /**
     * Verifies unregistering an inactive handler is idempotent.
     */
    public function testUnregisterNotRegisteredErrorHandlerShouldNotThrowExceptions(): void
    {
        $errorHandler = $this->container()->get(ErrorHandler::class);
        $errorHandler->unregister();
        static::assertTrue(true);
    }
}
