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

use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Verifies enabled-error metadata, disabled levels and actual @ suppression.
 *
 * @internal
 */
#[CoversClass(ThrowErrorExceptionPhpErrorHandler::class)]
#[UsesClass(ErrorException::class)]
final class ThrowErrorExceptionPhpErrorHandlerTest extends TestCase
{
    /** Enabled PHP errors retain their message, severity and supplied source location. */
    public function testConvertsEnabledErrorsWithoutLosingMetadata(): void
    {
        $previous = error_reporting(E_USER_WARNING);
        $caught = null;

        try {
            (new ThrowErrorExceptionPhpErrorHandler())->handlePhpError(
                E_USER_WARNING,
                'Synthetic enabled warning',
                '/synthetic/input.php',
                42,
            );
        } catch (ErrorException $error) {
            $caught = $error;
        } finally {
            error_reporting($previous);
        }

        self::assertInstanceOf(ErrorException::class, $caught);
        self::assertSame('Synthetic enabled warning', $caught->getMessage());
        self::assertSame(E_USER_WARNING, $caught->getCode());
        self::assertSame(E_USER_WARNING, $caught->getSeverity());
        self::assertSame('/synthetic/input.php', $caught->getFile());
        self::assertSame(42, $caught->getLine());
        self::assertNull($caught->getPrevious());
    }

    /**
     * A disabled error level returns normally without changing the application's mask.
     *
     * @param int $mask the application reporting mask that excludes user warnings
     */
    #[DataProvider('provideDoesNotConvertDisabledErrorLevelsCases')]
    public function testDoesNotConvertDisabledErrorLevels(int $mask): void
    {
        $previous = error_reporting($mask);

        try {
            (new ThrowErrorExceptionPhpErrorHandler())->handlePhpError(
                E_USER_WARNING,
                'Synthetic disabled warning',
                __FILE__,
                __LINE__,
            );
            self::assertSame($mask, error_reporting());
        } finally {
            error_reporting($previous);
        }
    }

    /** @return array<string, array{int}> disabled and unrelated reporting masks */
    public static function provideDoesNotConvertDisabledErrorLevelsCases(): iterable
    {
        return ['all disabled' => [0], 'another level enabled' => [E_USER_NOTICE]];
    }

    /** PHP's real suppression operator prevents conversion and restores its surrounding mask. */
    public function testHonorsActualAtSuppression(): void
    {
        $previous = error_reporting(E_ALL);
        $handler = new ThrowErrorExceptionPhpErrorHandler();

        try {
            @$handler->handlePhpError(E_USER_WARNING, 'Synthetic suppressed warning', __FILE__, __LINE__);
            self::assertSame(E_ALL, error_reporting());
        } finally {
            error_reporting($previous);
        }
    }
}
