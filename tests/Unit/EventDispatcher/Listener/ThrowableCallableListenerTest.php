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

namespace CoiSA\ErrorHandler\Test\Unit\EventDispatcher\Listener;

use CoiSA\ErrorHandler\EventDispatcher\Listener\ThrowableCallableListener;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Verifies callback return values cannot replace the listener's throwable event.
 *
 * @internal
 */
#[CoversClass(ThrowableCallableListener::class)]
final class ThrowableCallableListenerTest extends TestCase
{
    /**
     * Preserve the original event even when the callback returns another throwable.
     */
    public function testReturnsOriginalThrowableWhenCallbackReturnsReplacement(): void
    {
        $original = new \RuntimeException('Original event');
        $replacement = new \LogicException('Callback return value');
        $observed = null;

        $listener = new ThrowableCallableListener(
            /**
             * Observe the event and return a different object the adapter must ignore.
             *
             * @param \Throwable $throwable the event supplied to the callback
             *
             * @return \Throwable a replacement event that must not escape the listener
             */
            static function (\Throwable $throwable) use ($replacement, &$observed): \Throwable {
                $observed = $throwable;

                return $replacement;
            },
        );

        self::assertSame($original, $listener($original));
        self::assertSame($original, $observed);
    }
}
