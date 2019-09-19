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
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
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

    public function testErrorHandlerWithoutHandlerWillEchoOutput(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $this->expectOutputRegex('/^InvalidArgumentException: ' . $message . '/');
        $errorHandler->handleThrowable($exception);
    }

    public function testCallableHandlerWillHandleThrowable(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $callableThrowableHandler = new CallableThrowableHandler(function (\Throwable $throwable) use ($exception): void {
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

        $callableThrowableHandler = new CallableThrowableHandler(function (\Throwable $throwable) use ($exception): void {
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

        $errorEventCallableListener = new ErrorEventCallableListener(function (ErrorEventInterface $event) use ($exception): void {
            $this->assertInstanceOf(ErrorEvent::class, $event);
            $this->assertSame($exception, $event->getThrowable());
            $this->assertSame((string) $exception, (string) $event);
        });

        $throwableListener = new ThrowableCallableListener(function (\Throwable $throwable) use ($exception): void {
            $this->assertSame($exception, $throwable);
        });

        $this->listenerProvider->attach(new ErrorEventListenerProvider($errorEventCallableListener));
        $this->listenerProvider->attach(new ThrowableListenerProvider($throwableListener));

        $this->serviceManager->setService(EventDispatcherInterface::class, $this->eventDispatcher);

        $errorHandler = $this->container->get(ErrorHandler::class);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
    }
}
