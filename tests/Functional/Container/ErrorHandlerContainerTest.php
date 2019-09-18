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

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\ErrorHandler;
use PHPUnit\Framework\TestCase;

/**
 * Class ErrorHandlerContainerTest
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
 */
final class ErrorHandlerContainerTest extends TestCase
{
    public function testGetErrorHandlerWillReturnErrorHandler()
    {
        $container = new ErrorHandlerContainer();
        $errorHandler = $container->get(ErrorHandler::class);

        $this->assertInstanceOf(ErrorHandler::class, $errorHandler);
    }

    public function testGetErrorHandlerFromContainerWillHandleException()
    {
        $container = new ErrorHandlerContainer();

        $errorHandler = $container->get(ErrorHandler::class);
        $errorHandler->register();

        $message   = \uniqid('test', true);
        $exception = new \InvalidArgumentException($message);

        $this->expectOutputRegex('/^InvalidArgumentException: ' . $message . '/');
        $errorHandler->handleThrowable($exception);
    }
}
