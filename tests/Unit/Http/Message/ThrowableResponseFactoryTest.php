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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies safe HTTP status mapping, response bodies and output-buffer cleanup.
 *
 * @package CoiSA\ErrorHandler\Test\Unit\Http\Message
 *
 * @internal
 */
#[CoversClass(ThrowableResponseFactory::class)]
#[UsesClass(ThrowableStreamFactory::class)]
#[UsesClass(CallableThrowableHandler::class)]
final class ThrowableResponseFactoryTest extends TestCase
{
    /**
     * Verifies HTTP status mapping, generic bodies and private throwable delivery.
     *
     * @param int $code     the throwable code to map
     * @param int $expected the expected HTTP response status
     */
    #[DataProvider('provideMapsHttpErrorCodesAndDoesNotDiscloseExceptionDetailsCases')]
    public function testMapsHttpErrorCodesAndDoesNotDiscloseExceptionDetails(int $code, int $expected): void
    {
        $factory = new Psr17Factory();
        $reported = [];
        $reporter = new CallableThrowableHandler(static function (\Throwable $error) use (&$reported): void {
            $reported[] = $error;
        });
        $responses = new ThrowableResponseFactory(new ThrowableStreamFactory($factory, $reporter), $factory);
        $error = new \RuntimeException("private detail\r\nX-Injected: no", $code);
        $response = $responses->createResponseFromThrowable($error);

        self::assertSame($expected, $response->getStatusCode());
        self::assertStringNotContainsString('private detail', $response->getReasonPhrase());
        self::assertSame('Internal Server Error', (string) $response->getBody());
        self::assertSame([$error], $reported);
    }

    /**
     * Provides HTTP boundary codes and their expected response statuses.
     *
     * @return list<array{int, int}> input and expected HTTP status pairs
     */
    public static function provideMapsHttpErrorCodesAndDoesNotDiscloseExceptionDetailsCases(): iterable
    {
        return [[0, 500], [200, 500], [399, 500], [400, 400], [404, 404], [500, 500],
            [501, 501], [503, 503], [599, 599], [600, 500], [PHP_INT_MAX, 500]];
    }

    /**
     * Verifies actual and numeric-looking string SQLSTATEs remain HTTP 500.
     *
     * @param string $sqlState the original synthetic PDO code
     */
    #[DataProvider('provideStringSqlStateFallsBackTo500Cases')]
    public function testStringSqlStateFallsBackTo500(string $sqlState): void
    {
        $error = new class ($sqlState) extends \PDOException {
            /**
             * Supplies PDO's string code without using a database.
             *
             * @param string $sqlState the original synthetic SQLSTATE
             */
            public function __construct(string $sqlState)
            {
                $this->code = $sqlState;
            }
        };
        $factory = new Psr17Factory();
        $body = $factory->createStream('public response');
        $streams = $this->createMock(ThrowableStreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStreamFromThrowable')
            ->with(self::identicalTo($error))->willReturn($body)
        ;
        $response = (new ThrowableResponseFactory($streams, $factory))->createResponseFromThrowable($error);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getReasonPhrase());
        self::assertSame($body, $response->getBody());
        self::assertSame($sqlState, $error->getCode());
    }

    /** @return array<string, array{string}> true SQLSTATEs and HTTP-looking string codes */
    public static function provideStringSqlStateFallsBackTo500Cases(): iterable
    {
        return [
            'general error' => ['HY000'],
            'integrity constraint' => ['23000'],
            'HTTP-looking not found' => ['404'],
            'HTTP-looking lower bound' => ['400'],
            'HTTP-looking server error' => ['500'],
        ];
    }

    /**
     * Verifies the rendered string zero is preserved as a response body.
     */
    public function testZeroIsAValidRenderedBody(): void
    {
        $reporter = new CallableThrowableHandler(static function (): void {
            echo '0';
        });
        $streams = new ThrowableStreamFactory(new Psr17Factory(), $reporter);
        self::assertSame('0', (string) $streams->createStreamFromThrowable(new \RuntimeException('private')));
    }

    /**
     * Verifies successful nested rendering preserves the caller's output buffer.
     */
    public function testBalancedRendererBuffersPreserveCallerOutput(): void
    {
        $reporter = new CallableThrowableHandler(static function (): void {
            ob_start();
            echo 'nested body';
            $nested = ob_get_clean();
            echo 'public ' . $nested;
        });
        $streams = new ThrowableStreamFactory(new Psr17Factory(), $reporter);
        ob_start();
        echo 'caller output';
        $level = ob_get_level();

        try {
            $stream = $streams->createStreamFromThrowable(new \RuntimeException('private'));
            self::assertSame('public nested body', (string) $stream);
            self::assertSame($level, ob_get_level());
            self::assertSame('caller output', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Verifies renderer failure cleans nested buffers while preserving the caller buffer.
     */
    public function testRendererFailureCleansItsBuffersAndPreservesCallerBuffer(): void
    {
        $failure = new \RuntimeException('renderer failed');
        $reporter = new CallableThrowableHandler(static function () use ($failure): void {
            echo 'partial output';
            ob_start();
            echo 'nested output';

            throw $failure;
        });
        $streams = new ThrowableStreamFactory(new Psr17Factory(), $reporter);
        ob_start();
        echo 'caller output';
        $level = ob_get_level();

        try {
            $streams->createStreamFromThrowable(new \RuntimeException('original'));
            self::fail('Renderer failure must propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
            self::assertSame($level, ob_get_level());
            self::assertSame('caller output', ob_get_contents());
        } finally {
            ob_end_clean();
        }
    }
}
