<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
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
use CoiSA\Http\Handler\CallableHandler;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\StreamFactory;
use Zend\ServiceManager\ServiceManager;

/**
 * Class ErrorHandlerContainerTest
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
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

    public function setUp(): void
    {
        $this->serviceManager = new ServiceManager();
        $this->container      = new ErrorHandlerContainer($this->serviceManager);

        $this->listenerProvider = new ListenerProviderAggregate();
        $this->eventDispatcher  = new EventDispatcher($this->listenerProvider);

        $this->serviceManager->setService(ResponseFactoryInterface::class, new ResponseFactory());
        $this->serviceManager->setService(StreamFactoryInterface::class, new StreamFactory());
    }

    public function provideServiceProviderClassNames()
    {
        $factories = (new ConfigProvider())->getFactories();

        return \array_chunk(\array_keys($factories), 1);
    }

    /** @dataProvider provideServiceProviderClassNames */
    public function testContainerHasConfigProviderFactories(string $service): void
    {
        $this->assertTrue($this->container->has($service));
    }

    /** @dataProvider provideServiceProviderClassNames */
    public function testContainerProvideConfigProviderFactories(string $service): void
    {
        $this->serviceManager->setService(EventDispatcherInterface::class, $this->eventDispatcher);
        $this->assertInstanceOf($service, $this->container->get($service));
    }

    public function testErrorHandlerWithoutHandlerWillDoNothing(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $this->expectNotToPerformAssertions();
        $errorHandler->handleThrowable($exception);
    }

    public function testCallableHandlerWillHandleThrowable(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    public function testCallableHandlerFromAggregationWillHandleThrowable(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });
        $this->serviceManager->setService(CallableThrowableHandler::class, $callableThrowableHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    public function testEventDispatcherCallableHandlerWillHandleThrowable(): void
    {
        $message   = \uniqid('test', true);
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

        $this->serviceManager->setService(EventDispatcherInterface::class, $this->eventDispatcher);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }

    public function testErrorHandlerRespectErrorReporting(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable): void {
            $this->assertTrue(false);
        });
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $oldLevel = \error_reporting(E_ALL ^ E_USER_NOTICE);
        \trigger_error('Test error reporting', E_USER_NOTICE);
        \error_reporting($oldLevel);

        $this->assertTrue(true);
    }

    public function testThrowErrorExceptionPhpErrorHandlerThrowErrorException(): void
    {
        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable): void {
            $this->assertInstanceOf(ErrorException::class, $throwable);
        });
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        \trigger_error('Test user error', E_USER_ERROR);
    }

    public function testErrorHandlerMiddlewareHandleRequestException(): void
    {
        $exception = new \InvalidArgumentException(\uniqid('test', true), \random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            echo \json_encode([
                'code'    => $throwable->getCode(),
                'message' => $throwable->getMessage(),
            ]);
        });
        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $serverRequest  = new ServerRequest();
        $requestHandler = new CallableHandler(function () use ($exception): void {
            throw $exception;
        });

        $middleware = $this->container->get(ErrorHandlerMiddleware::class);
        $response   = $middleware->process($serverRequest, $requestHandler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame($exception->getCode(), $response->getStatusCode());

        $body = \json_decode((string) $response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertSame($exception->getCode(), $body['code']);
        $this->assertSame($exception->getMessage(), $body['message']);
    }

    public function testUnregisteredErrorHandlerHandleShutdownWillDoNothing(): void
    {
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->handleShutdown();

        $output = $this->getActualOutput();
        $this->assertEmpty($output);
    }

    public function testErrorHandlerHandleShutdownWithErrorWillHandledByThrowableHandler(): void
    {
        $exception = new \InvalidArgumentException(\uniqid('test', true), \random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(function ($throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });

        $callableShutdownHandler = new CallableShutdownHandler(function () use ($exception): void {
            throw $exception;
        });

        $this->serviceManager->setService(ThrowableHandlerInterface::class, $callableThrowableHandler);
        $this->serviceManager->setService(ShutdownHandlerInterface::class, $callableShutdownHandler);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleShutdown();
    }

    public function testUnregisterNotRegisteredErrorHandlerShouldNotThrowExceptions(): void
    {
        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->unregister();
        $this->assertTrue(true);
    }
}
