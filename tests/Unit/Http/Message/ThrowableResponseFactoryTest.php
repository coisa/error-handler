<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Unit\Http\Message;

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThrowableResponseFactoryTest extends TestCase
{
    public static function statusCodes(): array
    {
        return [[0, 500], [200, 500], [399, 500], [400, 400], [404, 404], [500, 500],
            [503, 503], [599, 599], [600, 500]];
    }

    #[DataProvider('statusCodes')]
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

    public function testStringSqlStateFallsBackTo500(): void
    {
        $error = new class () extends \PDOException {
            public function __construct()
            {
                $this->code = 'HY000';
            }
        };
        $factory = new Psr17Factory();
        $reporter = new CallableThrowableHandler(static function (): void {
        });
        $responses = new ThrowableResponseFactory(new ThrowableStreamFactory($factory, $reporter), $factory);
        self::assertSame(500, $responses->createResponseFromThrowable($error)->getStatusCode());
    }

    public function testZeroIsAValidRenderedBody(): void
    {
        $reporter = new CallableThrowableHandler(static function (): void {
            echo '0';
        });
        $streams = new ThrowableStreamFactory(new Psr17Factory(), $reporter);
        self::assertSame('0', (string) $streams->createStreamFromThrowable(new \RuntimeException('private')));
    }

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
