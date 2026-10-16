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

namespace CoiSA\ErrorHandler\Test\Unit\Http\Message;

use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;

/**
 * Verifies the 2.x response policy and delegation for throwable codes.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory
 */
final class ThrowableResponseFactoryTest extends TestCase
{
    /**
     * Preserves integer status mapping, reason phrases and the original throwable.
     *
     * @param int|string $code           the native exception code, including SQLSTATE strings
     * @param int        $expectedStatus The expected HTTP status under the 2.x policy.
     *
     * @dataProvider provideCreateResponsePreservesTheExistingPolicyCases
     */
    public function testCreateResponsePreservesTheExistingPolicy($code, int $expectedStatus): void
    {
        $throwable = new class ($code) extends \PDOException {
            /**
             * Creates a driver-free exception carrying the original code type.
             *
             * @param int|string $code the code a PDO driver could provide
             */
            public function __construct($code)
            {
                parent::__construct('Original diagnostic message');

                $this->code = $code;
            }
        };

        $psr17Factory = new Psr17Factory();
        $stream = $psr17Factory->createStream('Original delegated content');

        $streamFactory = $this->createMock(ThrowableStreamFactoryInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStreamFromThrowable')
            ->with(self::identicalTo($throwable))
            ->willReturn($stream)
        ;

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with($expectedStatus, $throwable->getMessage())
            ->willReturn($psr17Factory->createResponse($expectedStatus, $throwable->getMessage()))
        ;

        $factory = new ThrowableResponseFactory($streamFactory, $responseFactory);
        $response = $factory->createResponseFromThrowable($throwable);

        self::assertSame($code, $throwable->getCode());
        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame($throwable->getMessage(), $response->getReasonPhrase());
        self::assertSame($stream, $response->getBody());
        self::assertSame('Original delegated content', (string) $response->getBody());
    }

    /**
     * Supplies accepted integer statuses, range boundaries and string codes.
     *
     * @return array<string, array{int|string, int}> throwable codes and expected statuses
     */
    public static function provideCreateResponsePreservesTheExistingPolicyCases(): iterable
    {
        return [
            'lower boundary' => [400, 400],
            'ordinary client error' => [404, 404],
            'upper client error' => [499, 499],
            'upper boundary' => [500, 500],
            'below range' => [399, 500],
            'above range' => [501, 500],
            'unextended server error range' => [599, 500],
            'default exception code' => [0, 500],
            'negative code' => [-1, 500],
            'alphanumeric SQLSTATE' => ['HY000', 500],
            'numeric SQLSTATE' => ['23000', 500],
            'numeric-looking client error' => ['404', 500],
            'numeric-looking lower boundary' => ['400', 500],
            'numeric-looking upper boundary' => ['500', 500],
        ];
    }
}
