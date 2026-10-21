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

namespace CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\Factory\SymfonyCliThrowableHandlerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Verifies optional Console resolution stays inert and never hides explicit application failures.
 *
 * @internal
 */
#[CoversClass(SymfonyCliThrowableHandlerFactory::class)]
#[UsesClass(SymfonyCliThrowableHandler::class)]
final class SymfonyCliThrowableHandlerFactoryTest extends TestCase
{
    /** An absent optional output produces an inert native-stderr adapter without requesting other services. */
    public function testMissingOptionalOutputDoesNotRequireAnyService(): void
    {
        $this->expectOutputString('');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(OutputInterface::class)->willReturn(false);
        $container->expects(self::never())->method('get');

        self::assertInstanceOf(
            SymfonyCliThrowableHandler::class,
            (new SymfonyCliThrowableHandlerFactory())($container),
        );
    }

    /** A configured application output receives the diagnostic rather than native stderr. */
    public function testConfiguredOutputIsPassedUnchanged(): void
    {
        $this->expectOutputString('');
        $output = new BufferedOutput();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(OutputInterface::class)->willReturn(true);
        $container->expects(self::once())->method('get')->with(OutputInterface::class)->willReturn($output);
        $handler = (new SymfonyCliThrowableHandlerFactory())($container);
        self::assertSame('', $output->fetch());

        $handler->handleThrowable(new \RuntimeException('Configured CLI output'));

        self::assertStringContainsString('Configured CLI output', $output->fetch());
    }

    /** A malformed configured output cannot silently select native stderr. */
    public function testInvalidConfiguredOutputFails(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(OutputInterface::class)->willReturn(true);
        $container->expects(self::once())->method('get')->with(OutputInterface::class)->willReturn(new \stdClass());
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage(OutputInterface::class . ' must resolve to an OutputInterface instance.');

        (new SymfonyCliThrowableHandlerFactory())($container);
    }

    /** Explicit construction failures propagate unchanged rather than becoming an optional absence. */
    public function testConfiguredOutputFailureRemainsIdentical(): void
    {
        $failure = new \LogicException('CLI output construction failed');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(OutputInterface::class)->willReturn(true);
        $container->expects(self::once())->method('get')->with(OutputInterface::class)->willThrowException($failure);

        try {
            (new SymfonyCliThrowableHandlerFactory())($container);
            self::fail('The explicit output construction failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Discovery failures are not treated as an absent optional output. */
    public function testDiscoveryFailureRemainsIdentical(): void
    {
        $failure = new \LogicException('CLI output discovery failed');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('has')->with(OutputInterface::class)->willThrowException($failure);
        $container->expects(self::never())->method('get');

        try {
            (new SymfonyCliThrowableHandlerFactory())($container);
            self::fail('The output discovery failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
