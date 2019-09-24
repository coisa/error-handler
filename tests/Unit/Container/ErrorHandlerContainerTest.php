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

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class ErrorHandlerContainerTest
 *
 * @package CoiSA\ErrorHandler\Test\Unit\Container
 */
final class ErrorHandlerContainerTest extends TestCase
{
    /** @var ContainerInterface|ObjectProphecy */
    private $innerContainer;

    /** @var ErrorHandlerContainer */
    private $container;

    public function setUp(): void
    {
        $this->innerContainer = $this->prophesize(ContainerInterface::class);
        $this->container      = new ErrorHandlerContainer($this->innerContainer->reveal());
    }

    public function testGetWithInvalidServiceWillThrowNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get(\uniqid('test', true));
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
