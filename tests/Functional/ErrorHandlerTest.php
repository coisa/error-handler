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
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Symfony\Component\EventDispatcher\EventDispatcher;

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
        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::type(ErrorEventInterface::class))->shouldBeCalledOnce();

        $handler = new DispatchErrorEventThrowableHandler($eventDispatcher->reveal());

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler->handleThrowable($exception);
        $errorHandler->unregister();
    }

    public function test_error_handler_with_event_dispatcher_handler_and_log_error_event_listener_will_log_error_event(): void
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->log(LogLevel::ERROR, Argument::type('string'))->shouldBeCalledOnce();

        $listener = new LogErrorEventListener($logger->reveal());

        $eventDispatcher = $this->prophesize(EventDispatcher::class);
        $eventDispatcher->dispatch(Argument::type(ErrorEventInterface::class))->will(function () use ($listener): void {
            ($listener)(new ErrorEvent(new \Exception()));
        });

        $handler = new DispatchErrorEventThrowableHandler($eventDispatcher->reveal());

        $errorHandler = new ErrorHandler($handler);
        $errorHandler->register();

        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $errorHandler->handleThrowable($exception);
        $errorHandler->unregister();
    }
}
