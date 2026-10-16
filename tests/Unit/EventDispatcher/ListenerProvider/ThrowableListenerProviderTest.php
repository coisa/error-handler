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

namespace CoiSA\ErrorHandler\Test\Unit\EventDispatcher\ListenerProvider;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies Throwable selection, append order and lookup without listener execution.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ThrowableListenerProvider
 *
 * @uses \CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent
 */
final class ThrowableListenerProviderTest extends TestCase
{
    /**
     * Both Exception and Error events retain constructor and appended listener order.
     *
     * @param bool $isError whether the event is an Error rather than an Exception
     *
     * @dataProvider providePreservesListenerIdentityAndAttachmentOrderCases
     */
    public function testPreservesListenerIdentityAndAttachmentOrder(bool $isError): void
    {
        $calls = [];
        $first = $this->createListener('first', $calls);
        $second = $this->createListener('second', $calls);
        $third = $this->createListener('third', $calls);
        $event = $isError ? new \Error('Synthetic engine event') : new \RuntimeException('Synthetic exception');
        $provider = new ThrowableListenerProvider($first, $second);

        self::assertSame([$first, $second], [...$provider->getListenersForEvent($event)]);
        $provider->attach($third);
        self::assertSame([$first, $second, $third], [...$provider->getListenersForEvent($event)]);
        self::assertSame([], $calls);

        foreach ($provider->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        self::assertSame([['first', $event], ['second', $event], ['third', $event]], $calls);
    }

    /**
     * Supply independent native Throwable kinds without process-shared objects.
     *
     * @return array<string, array{bool}> event-kind inputs
     */
    public static function providePreservesListenerIdentityAndAttachmentOrderCases(): iterable
    {
        return ['exception' => [false], 'error' => [true]];
    }

    /**
     * An empty provider yields no listeners for a valid raw throwable.
     */
    public function testEmptyProviderHasNoListeners(): void
    {
        self::assertSame([], [...(new ThrowableListenerProvider())->getListenersForEvent(new \Error())]);
    }

    /**
     * Wrapped errors and unrelated objects are not raw Throwable events.
     */
    public function testRejectsWrappedAndUnrelatedEvents(): void
    {
        $calls = [];
        $provider = new ThrowableListenerProvider($this->createListener('unused', $calls));

        self::assertSame([], [...$provider->getListenersForEvent(new \stdClass())]);
        self::assertSame([], [...$provider->getListenersForEvent(new ErrorEvent(new \RuntimeException()))]);
        self::assertSame([], $calls);
    }

    /**
     * Build an observable callable without a separate dispatcher or test fixture class.
     *
     * @param string                            $name  the listener's independent identity
     * @param array<int, array{string, object}> $calls the shared invocation observations
     *
     * @return \Closure(object): void a callback recording its name and exact event
     */
    private function createListener(string $name, array &$calls): \Closure
    {
        return
            /**
             * Record one explicit dispatch by the test.
             *
             * @param object $event the unchanged event supplied to the listener
             */
            static function (object $event) use ($name, &$calls): void {
                $calls[] = [$name, $event];
            };
    }
}
