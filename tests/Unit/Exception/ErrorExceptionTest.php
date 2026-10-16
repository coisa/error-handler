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

namespace CoiSA\ErrorHandler\Test\Unit\Exception;

use CoiSA\ErrorHandler\Exception\ErrorException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies PHP-error metadata and original previous-throwable identity.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Exception\ErrorException
 */
final class ErrorExceptionTest extends TestCase
{
    /** All supplied metadata and the original causal object survive conversion. */
    public function testPreservesErrorMetadataAndPreviousThrowableIdentity(): void
    {
        $previous = new \RuntimeException('Synthetic original cause');
        $error = ErrorException::fromPhpError(
            'Synthetic PHP error',
            123,
            E_USER_NOTICE,
            '/synthetic/source.php',
            42,
            $previous,
        );

        self::assertSame('Synthetic PHP error', $error->getMessage());
        self::assertSame(123, $error->getCode());
        self::assertSame(E_USER_NOTICE, $error->getSeverity());
        self::assertSame('/synthetic/source.php', $error->getFile());
        self::assertSame(42, $error->getLine());
        self::assertSame($previous, $error->getPrevious());
    }

    /** Omitted nullable location metadata normalizes to native empty filename/zero line. */
    public function testNormalizesOmittedLocationWithoutInventingAPreviousThrowable(): void
    {
        $error = ErrorException::fromPhpError('Synthetic location-free error', 0, E_WARNING);

        self::assertSame('', $error->getFile());
        self::assertSame(0, $error->getLine());
        self::assertNull($error->getPrevious());
    }
}
