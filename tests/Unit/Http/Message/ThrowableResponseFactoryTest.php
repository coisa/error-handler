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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
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

    /**
     * Uses the application's default reason while preserving reporting and body capture.
     *
     * @param string $message a diagnostic containing a forbidden status-line byte
     *
     * @dataProvider provideInvalidReasonUsesPsr17DefaultCases
     */
    public function testInvalidReasonUsesPsr17Default(string $message): void
    {
        $throwable = new \RuntimeException($message, 404);
        $psr17Factory = new Psr17Factory();
        $entryLevel = ob_get_level();

        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())
            ->method('handleThrowable')
            ->with(self::identicalTo($throwable))
            ->willReturnCallback(
                /** Emits synthetic public output without changing the diagnostic. */
                static function (): void {
                    echo 'Public captured content';
                },
            )
        ;

        $factory = new ThrowableResponseFactory(new ThrowableStreamFactory($psr17Factory, $reporter), $psr17Factory);
        $response = $factory->createResponseFromThrowable($throwable);

        self::assertSame($message, $throwable->getMessage());
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame('Public captured content', (string) $response->getBody());
        self::assertSame($entryLevel, ob_get_level());
    }

    /**
     * Supplies every forbidden ASCII control byte and a real multiline diagnostic.
     *
     * @return iterable<string, array{string}> forbidden reason phrases
     */
    public static function provideInvalidReasonUsesPsr17DefaultCases(): iterable
    {
        foreach (range(0, 31) as $byte) {
            if (9 !== $byte) {
                yield \sprintf('control 0x%02X', $byte) => ['before' . \chr($byte) . 'after'];
            }
        }

        yield 'DEL' => ["before\x7Fafter"];

        yield 'CRLF diagnostic' => ["first\r\nsecond"];
    }

    /**
     * Preserves valid reason phrases, including an explicitly empty message.
     *
     * @param string $message        the original valid diagnostic
     * @param string $expectedReason the concrete PSR-17 implementation's reason
     *
     * @dataProvider provideValidReasonIsPreservedCases
     */
    public function testValidReasonIsPreserved(string $message, string $expectedReason): void
    {
        $throwable = new \RuntimeException($message, 400);
        $psr17Factory = new Psr17Factory();
        $stream = $psr17Factory->createStream('0');

        $streamFactory = $this->createMock(ThrowableStreamFactoryInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStreamFromThrowable')
            ->with(self::identicalTo($throwable))
            ->willReturn($stream)
        ;

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with(400, $message)
            ->willReturn($psr17Factory->createResponse(400, $message))
        ;

        $factory = new ThrowableResponseFactory($streamFactory, $responseFactory);
        $response = $factory->createResponseFromThrowable($throwable);

        self::assertSame($message, $throwable->getMessage());
        self::assertSame(400, $response->getStatusCode());
        self::assertSame($expectedReason, $response->getReasonPhrase());
        self::assertSame($stream, $response->getBody());
        self::assertSame('0', (string) $response->getBody());
    }

    /**
     * Supplies legal horizontal whitespace, visible ASCII and opaque high bytes.
     *
     * @return array<string, array{string, string}> valid messages and concrete reasons
     */
    public static function provideValidReasonIsPreservedCases(): iterable
    {
        return [
            'ordinary message' => ['Original diagnostic', 'Original diagnostic'],
            'empty message' => ['', ''],
            'space' => [' ', ' '],
            'horizontal tab' => ["before\tafter", "before\tafter"],
            'leading and trailing whitespace' => [" \tmessage\t ", " \tmessage\t "],
            'visible ASCII bounds' => ['!~', '!~'],
            'opaque high byte bounds' => ["\x80\xFF", "\x80\xFF"],
            'UTF-8 diagnostic' => ['Unexpected UTF-8: é', 'Unexpected UTF-8: é'],
        ];
    }

    /** Preserves response-factory failure identity before asking for a stream. */
    public function testResponseFactoryFailurePropagatesBeforeStreamCreation(): void
    {
        $throwable = new \RuntimeException("Original\nmessage");
        $failure = new \LogicException('Application response factory failed.');
        $streamFactory = $this->createMock(ThrowableStreamFactoryInterface::class);
        $streamFactory->expects(self::never())->method('createStreamFromThrowable');

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with(500)
            ->willThrowException($failure)
        ;

        try {
            (new ThrowableResponseFactory($streamFactory, $responseFactory))->createResponseFromThrowable($throwable);
            self::fail('The application response failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Preserves stream-factory failure identity after creating a valid response. */
    public function testStreamFactoryFailurePropagates(): void
    {
        $throwable = new \RuntimeException("Original\0message");
        $failure = new \LogicException('Application stream factory failed.');
        $psr17Factory = new Psr17Factory();

        $streamFactory = $this->createMock(ThrowableStreamFactoryInterface::class);
        $streamFactory->expects(self::once())
            ->method('createStreamFromThrowable')
            ->with(self::identicalTo($throwable))
            ->willThrowException($failure)
        ;

        $responseFactory = $this->createMock(ResponseFactoryInterface::class);
        $responseFactory->expects(self::once())
            ->method('createResponse')
            ->with(500)
            ->willReturn($psr17Factory->createResponse(500))
        ;

        try {
            (new ThrowableResponseFactory($streamFactory, $responseFactory))->createResponseFromThrowable($throwable);
            self::fail('The application stream failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
