<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Exception\NotFoundException;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Container\ContainerInterface;

/**
 * Verifies delegated missing-service and internal dependency-failure paths.
 *
 * @package CoiSA\ErrorHandler\Test\Unit\Container
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Container\ErrorHandlerContainer
 * @covers \CoiSA\ErrorHandler\Container\Exception\ContainerException
 */
final class ErrorHandlerContainerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * Parent-container double configured independently for each test.
     *
     * @var ObjectProphecy<ContainerInterface>
     */
    private $innerContainer;

    /** @var ErrorHandlerContainer */
    private $container;

    /**
     * Create an isolated parent-container double and the container under test.
     */
    protected function setUp(): void
    {
        $this->innerContainer = $this->prophesize(ContainerInterface::class);
        $this->container      = new ErrorHandlerContainer($this->innerContainer->reveal());
    }

    /**
     * Verify that an unknown service ID raises the library's not-found exception.
     */
    public function testGetWithInvalidServiceWillThrowNotFoundException(): void
    {
        $this->expectException(NotFoundException::class);
        $index = uniqid('test', true);
        $this->innerContainer->has($index)->willReturn(false);
        $this->container->get($index);
    }

    /**
     * Verify that a delegated dependency failure is wrapped as a container error.
     */
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
