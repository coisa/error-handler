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
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use FastForward\Container\Exception\AutowiringException;
use FastForward\Container\Exception\ContainerException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Verifies optional reporter discovery and propagation of construction failures.
 *
 * @package CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory
 *
 * @internal
 */
#[CoversClass(ThrowableHandlerAggregateFactory::class)]
#[UsesClass(ThrowableHandlerAggregate::class)]
#[UsesClass(CallableThrowableHandler::class)]
#[UsesClass(DispatchThrowableHandler::class)]
#[UsesClass(DispatchErrorEventThrowableHandler::class)]
#[UsesClass(ErrorEvent::class)]
final class ThrowableHandlerAggregateFactoryTest extends TestCase
{
    /** Verifies configured reporters remain inert until delivery and run once in public order. */
    public function testAvailableReportersDeliverTheOriginalInCallableRawWrappedOrder(): void
    {
        $original = new \Error('ordered factory delivery');
        $order = [];
        $callable = new CallableThrowableHandler(
            /**
             * Records the first route while retaining the original throwable.
             *
             * @param \Throwable $error the exact event delivered by the aggregate
             */
            static function (\Throwable $error) use (&$order, $original): void {
                self::assertSame($original, $error);
                $order[] = 'callable';
            },
        );
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            /**
             * Records raw and wrapped routes without replacing either dispatched object.
             *
             * @param object $event the raw throwable or its ErrorEvent wrapper
             *
             * @return object the same dispatched event
             */
            static function (object $event) use (&$order, $original): object {
                if ($event instanceof ErrorEvent) {
                    self::assertSame($original, $event->getThrowable());
                    $order[] = 'wrapped';
                } else {
                    self::assertSame($original, $event);
                    $order[] = 'raw';
                }

                return $event;
            },
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('has')->willReturnMap([
            [CallableThrowableHandler::class, true],
            [EventDispatcherInterface::class, true],
            [DispatchThrowableHandler::class, true],
            [DispatchErrorEventThrowableHandler::class, true],
        ]);
        $container->expects(self::exactly(3))->method('get')->willReturnMap([
            [CallableThrowableHandler::class, $callable],
            [DispatchThrowableHandler::class, new DispatchThrowableHandler($dispatcher)],
            [DispatchErrorEventThrowableHandler::class, new DispatchErrorEventThrowableHandler($dispatcher)],
        ]);

        $aggregate = (new ThrowableHandlerAggregateFactory())($container);
        self::assertSame([], $order);
        $aggregate->handleThrowable($original);

        self::assertSame(['callable', 'raw', 'wrapped'], $order);
    }

    /** Verifies callable reporting works without resolving or probing unavailable dispatch adapters. */
    public function testCallableReporterDoesNotRequireAnEventDispatcher(): void
    {
        $original = new \RuntimeException('callable without dispatcher');
        $received = [];
        $callable = new CallableThrowableHandler(
            /** @param \Throwable $error The event received through the optional callable route. */
            static function (\Throwable $error) use (&$received): void {
                $received[] = $error;
            },
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('has')->willReturnMap([
            [CallableThrowableHandler::class, true],
            [EventDispatcherInterface::class, false],
        ]);
        $container->expects(self::once())->method('get')->with(CallableThrowableHandler::class)->willReturn($callable);

        $aggregate = (new ThrowableHandlerAggregateFactory())($container);
        self::assertSame([], $received);
        $aggregate->handleThrowable($original);

        self::assertSame([$original], $received);
    }

    /**
     * Verifies an available dispatch adapter remains usable when its companion is absent.
     *
     * @param bool $wrapped whether only the ErrorEvent adapter is available
     */
    #[DataProvider('provideOnlyTheAvailableDispatchAdapterIsAttachedCases')]
    public function testOnlyTheAvailableDispatchAdapterIsAttached(bool $wrapped): void
    {
        $original = new \RuntimeException('partial dispatch composition');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
            /**
             * Requires the configured raw or wrapped form to retain the exact original.
             *
             * @param mixed $event the dispatched value observed by the mock constraint
             *
             * @return bool whether the one available adapter delivered its expected event
             */
            static fn(mixed $event): bool => $wrapped
                ? $event instanceof ErrorEvent && $event->getThrowable() === $original
                : $event === $original,
        ))->willReturnArgument(0);
        $adapter = $wrapped
            ? new DispatchErrorEventThrowableHandler($dispatcher)
            : new DispatchThrowableHandler($dispatcher);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('has')->willReturnMap([
            [CallableThrowableHandler::class, false],
            [EventDispatcherInterface::class, true],
            [DispatchThrowableHandler::class, !$wrapped],
            [DispatchErrorEventThrowableHandler::class, $wrapped],
        ]);
        $container->expects(self::once())->method('get')->with($adapter::class)->willReturn($adapter);

        (new ThrowableHandlerAggregateFactory())($container)->handleThrowable($original);
    }

    /** @return iterable<string, array{bool}> Available raw or wrapped dispatch routes. */
    public static function provideOnlyTheAvailableDispatchAdapterIsAttachedCases(): iterable
    {
        yield 'raw only' => [false];

        yield 'wrapped only' => [true];
    }

    /**
     * Verifies an unavailable optional callable does not prevent either event route.
     *
     * @param \Throwable $missing the missing or structurally unconfigured callable failure
     */
    #[DataProvider('provideAnUnavailableOptionalCallableDoesNotPreventEventDeliveryCases')]
    public function testAnUnavailableOptionalCallableDoesNotPreventEventDelivery(\Throwable $missing): void
    {
        $original = new \RuntimeException('Event-only reporting');
        $events = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            /**
             * Retains both deliveries for post-invocation identity assertions.
             *
             * @param object $event the raw throwable or ErrorEvent wrapper
             *
             * @return object the unchanged event
             */
            static function (object $event) use (&$events): object {
                $events[] = $event;

                return $event;
            },
        );
        $raw = new DispatchThrowableHandler($dispatcher);
        $wrapped = new DispatchErrorEventThrowableHandler($dispatcher);
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('has')->willReturn(true);
        $container->expects(self::exactly(3))->method('get')->willReturnCallback(
            /**
             * Models optional lookup failure while retaining both configured adapters.
             *
             * @param string $id the requested reporter service
             *
             * @return DispatchErrorEventThrowableHandler|DispatchThrowableHandler the available adapter
             */
            static fn(string $id): DispatchThrowableHandler|DispatchErrorEventThrowableHandler => match ($id) {
                CallableThrowableHandler::class => throw $missing,
                DispatchThrowableHandler::class => $raw,
                DispatchErrorEventThrowableHandler::class => $wrapped,
                default => throw new \LogicException('Unexpected reporter lookup: ' . $id),
            },
        );

        $aggregate = (new ThrowableHandlerAggregateFactory())($container);
        self::assertCount(0, $events);
        $aggregate->handleThrowable($original);

        self::assertCount(2, $events);
        self::assertSame($original, $events[0]);
        self::assertInstanceOf(ErrorEvent::class, $events[1]);
        self::assertSame($original, $events[1]->getThrowable());
    }

    /** @return iterable<string, array{\Throwable}> Optional callable lookup failures that allow event reporting. */
    public static function provideAnUnavailableOptionalCallableDoesNotPreventEventDeliveryCases(): iterable
    {
        yield 'missing service' => [
            new class ('Missing callable') extends \RuntimeException implements NotFoundExceptionInterface {
            },
        ];

        yield 'structurally unconfigured callable' => [
            AutowiringException::forUnresolvableParameter(CallableThrowableHandler::class, 'callable', 'callable'),
        ];
    }

    /** Verifies structural failures outside the optional callable lookup are not skipped. */
    public function testDispatchAdapterAutowiringFailuresPropagate(): void
    {
        $failure = AutowiringException::forUnresolvableParameter(
            DispatchThrowableHandler::class,
            'eventDispatcher',
            EventDispatcherInterface::class,
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturnCallback(
            static fn(string $id): bool => CallableThrowableHandler::class !== $id,
        );
        $container->expects(self::once())->method('get')
            ->with(DispatchThrowableHandler::class)->willThrowException($failure)
        ;
        $this->expectExceptionObject($failure);

        (new ThrowableHandlerAggregateFactory())($container);
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

        try {
            (new ThrowableHandlerAggregateFactory())($container);
            self::fail('Unexpectedly swallowed an explicit construction failure.');
        } catch (\Throwable $caught) {
            self::assertSame($failure, $caught);
            self::assertSame($failure->getPrevious(), $caught->getPrevious());
        }
    }

    /**
     * Provides PSR container and ordinary application construction failures.
     *
     * @return iterable<string, array{\Throwable}> synthetic construction failures
     */
    public static function provideConstructionErrorsAreNotHiddenCases(): iterable
    {
        yield 'PSR construction failure' => [
            new class ('Invalid reporter') extends \RuntimeException implements ContainerExceptionInterface {
            },
        ];

        yield 'application failure' => [new \LogicException('Reporter initialization failed')];

        yield 'explicit factory structural failure' => [
            ContainerException::forInvalidService(
                CallableThrowableHandler::class,
                AutowiringException::forUnresolvableParameter(CallableThrowableHandler::class, 'callable', 'callable'),
            ),
        ];
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
