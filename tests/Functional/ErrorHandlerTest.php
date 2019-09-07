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

namespace CoiSA\ErrorHandler\Test\Functional;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ErrorEventCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Class ErrorHandlerTest
 *
 * @package CoiSA\ErrorHandler\Test\Functional
 */
final class ErrorHandlerTest extends TestCase
{
    public function test_error_handler_will_handle_exception(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $handler = new CallableThrowableHandler(function (\Throwable $throwable) use ($exception): void {
            $this->assertInstanceOf(\Throwable::class, $throwable);
            $this->assertSame($exception, $throwable);
        });

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
        $errorHandler->unregister();
    }

    public function test_error_handler_will_catch_exception(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $handler = new CallableThrowableHandler(function (\Throwable $throwable) use ($exception): void {
            $this->assertInstanceOf(\Throwable::class, $throwable);
            $this->assertSame($exception, $throwable);
        });

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $this->expectException(\InvalidArgumentException::class);

        throw $exception;
    }

    public function test_error_handler_will_catch_php_error(): void
    {
        $handler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            $this->assertInstanceOf(ErrorException::class, $throwable);
        });

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $this->expectException(ErrorException::class);
        \trigger_error(\uniqid('test', true));
    }

    public function test_error_handler_will_be_silent_when_error_reporting_set_not_to_listen(): void
    {
        \error_reporting(E_ALL ^ E_USER_WARNING);

        $handler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            $this->assertInstanceOf(ErrorException::class, $throwable);
        });

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        \trigger_error(\uniqid('test', true), E_USER_WARNING);
        $this->expectNotToPerformAssertions();
    }

    public function test_error_handler_will_handle_php_error(): void
    {
        $handler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            $this->assertInstanceOf(ErrorException::class, $throwable);
        });

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $this->expectException(ErrorException::class);

        $errorHandler->handlePhpError(
            \random_int(1, 100),
            \uniqid('test', true),
            \tempnam(\sys_get_temp_dir(), 'test'),
            \random_int(1, 100)
        );

        $errorHandler->unregister();
    }

    public function test_error_handler_with_event_dispatcher_handler_will_dispatch_error_event(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $eventDispatcher = $this->getEventDispatcher($exception);

        $handler      = new DispatchErrorEventThrowableHandler($eventDispatcher);
        $errorHandler = new ErrorHandler($handler);

        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
        $errorHandler->unregister();
    }

    public function test_error_handler_with_dispatch_throwable_handler_will_dispatch_throwable(): void
    {
        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $eventDispatcher = $this->getEventDispatcher($exception);

        $handler      = new DispatchThrowableHandler($eventDispatcher);
        $errorHandler = new ErrorHandler($handler);

        $errorHandler->register();

        $errorHandler->handleThrowable($exception);
        $errorHandler->unregister();
    }

    private function getTestLogger(TestCase $test)
    {
        return new class($test) extends AbstractLogger {
            private $test;

            public function __construct(TestCase $test)
            {
                $this->test = $test;
            }

            public function log($level, $message, array $context = []): void
            {
                $this->test::assertTrue(true);
            }
        };
    }

    /**
     * @param \InvalidArgumentException $exception
     *
     * @return EventDispatcher
     */
    private function getEventDispatcher(\InvalidArgumentException $exception): EventDispatcher
    {
        $listenerProvider = new ListenerProviderAggregate();

        $listenerProvider->attach(
            new ThrowableListenerProvider(
                new ThrowableCallableListener(function (\Throwable $throwable) use ($exception): void {
                    $this->assertSame($exception, $throwable);
                })
            )
        );

        $listenerProvider->attach(
            new ErrorEventListenerProvider(
                new LogErrorEventListener($this->getTestLogger($this)),
                new ErrorEventCallableListener(function (ErrorEventInterface $errorEvent) use ($exception): void {
                    $this->assertSame($exception, $errorEvent->getTrowable());
                })
            )
        );

        return new EventDispatcher($listenerProvider);
    }
}
