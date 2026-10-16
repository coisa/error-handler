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

use CoiSA\ErrorHandler\ServiceProvider\Factory\ThrowableHandlerAggregateFactory;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Verifies optional reporter discovery and propagation of construction failures.
 *
 * @package CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory
 *
 * @internal
 *
 * @coversNothing
 */
final class ThrowableHandlerAggregateFactoryTest extends TestCase
{
    /**
     * Verifies an unconstructible optional callable does not prevent aggregate construction.
     */
    public function testAnAdvertisedButMissingOptionalCallableIsSkipped(): void
    {
        $missing = new class ('Missing callable') extends \RuntimeException implements NotFoundExceptionInterface {};
        $container = $this->containerFailingWith($missing);
        $aggregate = (new ThrowableHandlerAggregateFactory())($container);

        $this->expectOutputString('');
        $aggregate->handleThrowable(new \RuntimeException('No reporter'));
    }

    /**
     * Verifies reporter construction failures other than not-found propagate.
     *
     * @param \Throwable $failure the synthetic failure to propagate
     */
    #[DataProvider('provideConstructionErrorsAreNotHiddenCases')]
    public function testConstructionErrorsAreNotHidden(\Throwable $failure): void
    {
        $container = $this->containerFailingWith($failure);
        $this->expectExceptionObject($failure);

        (new ThrowableHandlerAggregateFactory())($container);
    }

    /**
     * Provides PSR container and ordinary application construction failures.
     *
     * @return iterable<string, array{\Throwable}> synthetic construction failures
     */
    public static function provideConstructionErrorsAreNotHiddenCases(): iterable
    {
        yield 'PSR construction failure' => [
            new class ('Invalid reporter') extends \RuntimeException implements ContainerExceptionInterface {},
        ];

        yield 'application failure' => [new \LogicException('Reporter initialization failed')];
    }

    /**
     * Creates a container double that advertises a reporter but fails its lookup.
     *
     * @param \Throwable $failure the synthetic failure to propagate
     *
     * @return ContainerInterface the fixture result
     */
    private function containerFailingWith(\Throwable $failure): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn(string $id): bool => CallableThrowableHandler::class === $id,
        );
        $container->expects(self::once())->method('get')
            ->with(CallableThrowableHandler::class)->willThrowException($failure)
        ;

        return $container;
    }
}
