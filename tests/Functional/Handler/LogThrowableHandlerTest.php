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

namespace CoiSA\ErrorHandler\Test\Functional\Handler;

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Verifies explicit logging composition without changing legacy default discovery.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\LogThrowableHandler
 *
 * @uses \CoiSA\ErrorHandler\Container\ErrorHandlerContainer
 * @uses \CoiSA\ErrorHandler\ErrorHandler
 */
final class LogThrowableHandlerTest extends TestCase
{
    /**
     * A parent logger alone must leave the default reporter aggregate silent.
     */
    public function testAvailableLoggerIsNotDiscoveredByTheDefaultAggregate(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('log');
        $application = $this->createMock(ContainerInterface::class);
        $application->method('has')->willReturnCallback(
            /**
             * Advertise only the application logger, without adding a reporter binding.
             *
             * @param string $id the requested service identifier
             *
             * @return bool whether the logger service is requested
             */
            static fn(string $id): bool => LoggerInterface::class === $id,
        );
        $application->expects(self::never())->method('get');
        $services = new ErrorHandlerContainer($application);
        $handler = $services->get(ErrorHandler::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);

        $this->expectOutputString('');
        $handler->handleThrowable(new \RuntimeException('Synthetic default report'));
    }

    /**
     * An application can bind its process handler explicitly without global discovery.
     */
    public function testExplicitApplicationHandlerUsesTheLoggerOnce(): void
    {
        $throwable = new \RuntimeException('Synthetic explicit report');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('log')->with(
            LogLevel::ERROR,
            $throwable->getMessage(),
            ['exception' => $throwable],
        );
        $handler = new ErrorHandler(new LogThrowableHandler($logger));
        $application = $this->createMock(ContainerInterface::class);
        $application->method('has')->with(ErrorHandler::class)->willReturn(true);
        $application->method('get')->with(ErrorHandler::class)->willReturn($handler);
        $services = new ErrorHandlerContainer($application);
        $resolved = $services->get(ErrorHandler::class);
        self::assertSame($handler, $resolved);

        $this->expectOutputString('');
        $handler->handleThrowable($throwable);
    }
}
