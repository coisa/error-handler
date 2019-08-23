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
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use PHPUnit\Framework\TestCase;

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
}
