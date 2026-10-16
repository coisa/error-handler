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

namespace CoiSA\ErrorHandler\Test\Unit\EventDispatcher\Event;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Verifies a wrapped event retains the original throwable and its full string form.
 *
 * @internal
 */
#[CoversClass(ErrorEvent::class)]
final class ErrorEventTest extends TestCase
{
    /**
     * Preserve identity and the throwable's diagnostic representation, including empty messages.
     *
     * @param bool   $isError whether the original is an Error instead of an Exception
     * @param string $message the synthetic throwable's message
     */
    #[DataProvider('provideRetainsTheOriginalThrowableAndStringCases')]
    public function testRetainsTheOriginalThrowableAndString(bool $isError, string $message): void
    {
        $original = $isError ? new \Error($message) : new \RuntimeException($message);
        $event = new ErrorEvent($original);

        self::assertSame($original, $event->getThrowable());
        self::assertSame((string) $original, (string) $event);
    }

    /**
     * Supply independent engine, exception and empty-message boundaries.
     *
     * @return array<string, array{bool, string}> throwable inputs created inside each test
     */
    public static function provideRetainsTheOriginalThrowableAndStringCases(): iterable
    {
        return [
            'exception' => [false, 'Synthetic exception'],
            'error' => [true, 'Synthetic error'],
            'empty message' => [false, ''],
        ];
    }
}
