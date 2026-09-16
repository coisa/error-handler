<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\Factory\ThrowableHandlerAggregateFactory;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

final class ThrowableHandlerAggregateFactoryTest extends TestCase
{
    public function testAnAdvertisedButMissingOptionalCallableIsSkipped(): void
    {
        $missing = new class ('Missing callable') extends \RuntimeException implements NotFoundExceptionInterface {
        };
        $container = $this->containerFailingWith($missing);
        $aggregate = (new ThrowableHandlerAggregateFactory())($container);

        $this->expectOutputString('');
        $aggregate->handleThrowable(new \RuntimeException('No reporter'));
    }

    #[DataProvider('constructionFailures')]
    public function testConstructionErrorsAreNotHidden(\Throwable $failure): void
    {
        $container = $this->containerFailingWith($failure);
        $this->expectExceptionObject($failure);

        (new ThrowableHandlerAggregateFactory())($container);
    }

    public static function constructionFailures(): iterable
    {
        yield 'PSR construction failure' => [
            new class ('Invalid reporter') extends \RuntimeException implements ContainerExceptionInterface {
            },
        ];
        yield 'application failure' => [new \LogicException('Reporter initialization failed')];
    }

    private function containerFailingWith(\Throwable $failure): ContainerInterface
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn (string $id): bool => $id === CallableThrowableHandler::class
        );
        $container->expects(self::once())->method('get')
            ->with(CallableThrowableHandler::class)->willThrowException($failure);

        return $container;
    }
}
