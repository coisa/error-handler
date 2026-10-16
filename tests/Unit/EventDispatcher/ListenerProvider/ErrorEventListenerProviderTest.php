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
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies concrete-event selection and stable listener order without dispatch side effects.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\EventDispatcher\ListenerProvider\ErrorEventListenerProvider
 *
 * @uses \CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent
 */
final class ErrorEventListenerProviderTest extends TestCase
{
    /**
     * Retain constructor and appended callables in order without invoking them during lookup.
     */
    public function testPreservesListenerIdentityAndAttachmentOrder(): void
    {
        $calls = [];
        $first = $this->createListener('first', $calls);
        $second = $this->createListener('second', $calls);
        $third = $this->createListener('third', $calls);
        $event = new ErrorEvent(new \RuntimeException('Synthetic wrapped event'));
        $provider = new ErrorEventListenerProvider($first, $second);

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
     * An empty provider yields no listeners for a valid wrapped event.
     */
    public function testEmptyProviderHasNoListeners(): void
    {
        $event = new ErrorEvent(new \Error('Synthetic wrapped error'));

        self::assertSame([], [...(new ErrorEventListenerProvider())->getListenersForEvent($event)]);
    }

    /**
     * The concrete ErrorEvent boundary excludes unrelated events and interface-only objects.
     */
    public function testRejectsUnrelatedAndInterfaceOnlyEvents(): void
    {
        $calls = [];
        $provider = new ErrorEventListenerProvider($this->createListener('unused', $calls));

        self::assertSame([], [...$provider->getListenersForEvent(new \stdClass())]);
        self::assertSame([], [...$provider->getListenersForEvent($this->createMock(ErrorEventInterface::class))]);
        self::assertSame([], [...$provider->getListenersForEvent(new \RuntimeException('Raw throwable'))]);
        self::assertSame([], $calls);
    }

    /**
     * Build an observable callable without coupling lookup to a dispatcher implementation.
     *
     * @param string                            $name  the listener's independent identity
     * @param array<int, array{string, object}> $calls the shared invocation observations
     *
     * @return \Closure(object): void a callback that records its name and exact event
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
