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

namespace CoiSA\ErrorHandler\Test\Unit\Handler;

use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use PHPUnit\Framework\TestCase;

/**
 * Verifies real absent/nonfatal last-error states without faking native PHP functions.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler
 */
final class PhpLastErrorShutdownHandlerTest extends TestCase
{
    /**
     * No last error means no shutdown delivery.
     *
     * @runInSeparateProcess
     */
    public function testDoesNothingWhenNoLastErrorExists(): void
    {
        error_clear_last();
        $delegate = $this->createMock(PhpErrorHandlerInterface::class);
        $delegate->expects(self::never())->method('handlePhpError');
        self::assertNull(error_get_last());

        (new PhpLastErrorShutdownHandler($delegate))->handleShutdown();

        self::assertNull(error_get_last());
    }

    /**
     * A real nonfatal last warning is ignored; restore callbacks/mask and clear fixture state.
     *
     * @runInSeparateProcess
     */
    public function testDoesNotReportARealNonfatalLastError(): void
    {
        $delegate = $this->createMock(PhpErrorHandlerInterface::class);
        $delegate->expects(self::never())->method('handlePhpError');
        $previous = error_reporting(0);

        /** @return bool allow PHP to retain its native last-error record */
        $observe = static fn(): bool => false;
        set_error_handler($observe);
        error_clear_last();

        try {
            trigger_error('Synthetic nonfatal shutdown input', E_USER_WARNING);
            $last = error_get_last();
            self::assertIsArray($last);
            self::assertSame(E_USER_WARNING, $last['type']);
            self::assertSame('Synthetic nonfatal shutdown input', $last['message']);

            (new PhpLastErrorShutdownHandler($delegate))->handleShutdown();

            self::assertSame($last, error_get_last());
        } finally {
            restore_error_handler();
            error_reporting($previous);
            error_clear_last();
        }
    }
}
