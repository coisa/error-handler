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

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Container\ContainerInterface;

/**
 * Class ErrorHandlerContainerTest.
 *
 * @package CoiSA\ErrorHandler\Test\Unit\Container
 *
 * @internal
 * @coversNothing
 */
final class ErrorHandlerContainerTest extends TestCase
{
    /** @var ContainerInterface&Stub */
    private $innerContainer;

    /** @var ErrorHandlerContainer */
    private $container;

    protected function setUp(): void
    {
        $this->innerContainer = $this->createStub(ContainerInterface::class);
        $this->container      = new ErrorHandlerContainer($this->innerContainer);
    }

    public function testGetWithInvalidServiceWillThrowNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $index = uniqid('test', true);
        $this->innerContainer->method('has')->willReturn(false);
        $this->container->get($index);
    }

    public function testGetWithServiceThatThrowsExceptionWillThrowContainerException(): void
    {
        $this->innerContainer->method('has')->willReturnMap([
            [ErrorHandler::class, false],
            [ThrowableHandlerInterface::class, true],
        ]);
        $this->innerContainer->method('get')->willThrowException(new \UnexpectedValueException());

        $container = new ErrorHandlerContainer($this->innerContainer);

        $this->expectException(ContainerException::class);
        $container->get(ErrorHandler::class);
    }
}
