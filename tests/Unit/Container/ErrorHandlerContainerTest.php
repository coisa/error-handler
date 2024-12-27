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
use Prophecy\Prophecy\ObjectProphecy;
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
    /** @var ContainerInterface|ObjectProphecy */
    private $innerContainer;

    /** @var ErrorHandlerContainer */
    private $container;

    protected function setUp(): void
    {
        $this->innerContainer = $this->prophesize(ContainerInterface::class);
        $this->container      = new ErrorHandlerContainer($this->innerContainer->reveal());
    }

    public function testGetWithInvalidServiceWillThrowNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $index = uniqid('test', true);
        $this->innerContainer->has($index)->willReturn(false);
        $this->container->get($index);
    }

    public function testGetWithServiceThatThrowsExceptionWillThrowContainerException(): void
    {
        $this->innerContainer->has(ErrorHandler::class)->willReturn(false);
        $this->innerContainer->has(ThrowableHandlerInterface::class)->willReturn(true);
        $this->innerContainer->get(ThrowableHandlerInterface::class)->willThrow(\UnexpectedValueException::class);

        $container = new ErrorHandlerContainer($this->innerContainer->reveal());

        $this->expectException(ContainerException::class);
        $container->get(ErrorHandler::class);
    }
}
