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

use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Verifies explicit JSON status, representation and PSR factory failure boundaries.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory
 */
final class JsonThrowableResponseFactoryTest extends TestCase
{
    /** Requires rendering to return data without printing diagnostics. */
    protected function setUp(): void
    {
        $this->expectOutputString('');
    }

    /**
     * Preserves only integer HTTP error codes and never publishes throwable details.
     *
     * @param int|string $code           the native exception or SQLSTATE-like code
     * @param int        $expectedStatus the public HTTP error status
     *
     * @dataProvider provideCreatesGenericJsonWithoutUsingDiagnosticMessagesCases
     */
    public function testCreatesGenericJsonWithoutUsingDiagnosticMessages($code, int $expectedStatus): void
    {
        $throwable = new class ($code) extends \PDOException {
            /**
             * Carries native string codes without requiring a database driver.
             *
             * @param int|string $code the original database or HTTP-looking code
             */
            public function __construct($code)
            {
                parent::__construct(
                    'Sensitive credential and query',
                    0,
                    new \RuntimeException('Private previous cause'),
                );

                $this->code = $code;
            }
        };
        $messages = new Psr17Factory();
        $streams = $this->createMock(StreamFactoryInterface::class);
        $stream = $messages->createStream('{"error":"Request failed"}');
        $streams->expects(self::once())->method('createStream')
            ->with('{"error":"Request failed"}')
            ->willReturn($stream)
        ;
        $bufferLevel = ob_get_level();

        $response = (new JsonThrowableResponseFactory($messages, $streams))->createResponseFromThrowable($throwable);

        self::assertSame($code, $throwable->getCode());
        self::assertSame($expectedStatus, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getHeaderLine('Cache-Control'));
        self::assertSame($messages->createResponse($expectedStatus)->getReasonPhrase(), $response->getReasonPhrase());
        self::assertSame($stream, $response->getBody());
        self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
        self::assertSame(
            ['error' => 'Request failed'],
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame($bufferLevel, ob_get_level());
    }

    /**
     * Supplies accepted boundaries, server errors and type-sensitive fallbacks.
     *
     * @return array<string, array{int|string, int}> native input codes and public statuses
     */
    public static function provideCreatesGenericJsonWithoutUsingDiagnosticMessagesCases(): iterable
    {
        return [
            'lower boundary' => [400, 400],
            'client error' => [404, 404],
            'server boundary' => [500, 500],
            'server error' => [503, 503],
            'upper boundary' => [599, 599],
            'below range' => [399, 500],
            'above range' => [600, 500],
            'default code' => [0, 500],
            'negative code' => [-1, 500],
            'numeric-looking client error' => ['404', 500],
            'numeric-looking upper boundary' => ['599', 500],
            'numeric SQLSTATE' => ['23000', 500],
            'alphanumeric SQLSTATE' => ['HY000', 500],
        ];
    }

    /** Each rendering receives a fresh application response and body. */
    public function testCreatesFreshResponsesAndBodiesForRepeatedCalls(): void
    {
        $messages = new Psr17Factory();
        $factory = new JsonThrowableResponseFactory($messages, $messages);
        $throwable = new \Error('Private engine details', 500);

        $first = $factory->createResponseFromThrowable($throwable);
        $second = $factory->createResponseFromThrowable($throwable);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->getBody(), $second->getBody());
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
    }

    /** The response implementation owns its default reason phrase. */
    public function testDoesNotSupplyAThrowableReasonPhraseToTheResponseFactory(): void
    {
        $messages = new Psr17Factory();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')
            ->with(404)
            ->willReturn($messages->createResponse(404, 'Implementation default'))
        ;

        $response = (new JsonThrowableResponseFactory($responses, $messages))
            ->createResponseFromThrowable(new \RuntimeException('Private diagnostic reason', 404))
        ;

        self::assertSame('Implementation default', $response->getReasonPhrase());
    }

    /** A failed response construction prevents body construction and retains identity. */
    public function testPropagatesTheIdenticalResponseFactoryFailure(): void
    {
        $failure = new \RuntimeException('Response construction failed');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->willThrowException($failure);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $caught = null;

        try {
            (new JsonThrowableResponseFactory($responses, $streams))
                ->createResponseFromThrowable(new \RuntimeException('Original failure'))
            ;
        } catch (\Throwable $actual) {
            $caught = $actual;
        }

        self::assertSame($failure, $caught);
    }

    /** A failed body construction propagates without fallback or retry. */
    public function testPropagatesTheIdenticalStreamFactoryFailure(): void
    {
        $failure = new \RuntimeException('Stream construction failed');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')->willThrowException($failure);
        $caught = null;

        try {
            (new JsonThrowableResponseFactory(new Psr17Factory(), $streams))
                ->createResponseFromThrowable(new \RuntimeException('Original failure'))
            ;
        } catch (\Throwable $actual) {
            $caught = $actual;
        }

        self::assertSame($failure, $caught);
    }

    /** Debug is explicit, preserves native metadata and excludes private trace arguments. */
    public function testDebugIncludesOnlySelectedNativeTraceMetadata(): void
    {
        $argument = new class implements \JsonSerializable {
            /**
             * Rejects accidental traversal into an application object from a native trace.
             *
             * @return array<string, string> no data is returned when the trace boundary is respected
             *
             * @throws \LogicException if the renderer serializes private arguments
             */
            public function jsonSerialize(): array
            {
                throw new \LogicException('Trace arguments must never be serialized.');
            }
        };
        $previous = \ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');

        try {
            $marker = 'Private trace argument marker';
            $throwable = $this->createDiagnosticThrowable($argument, $marker);
            $nativeFrame = $throwable->getTrace()[0];
            self::assertSame([$argument, $marker], $nativeFrame['args'] ?? []);
            $messages = new Psr17Factory();
            $bodyFactory = $this->createMock(StreamFactoryInterface::class);
            $bodyFactory->expects(self::once())->method('createStream')->willReturnCallback(
                /**
                 * Supply one real body for the encoded document without capturing output.
                 *
                 * @param string $body the renderer's complete JSON document
                 *
                 * @return StreamInterface the corresponding application stream
                 */
                static fn(string $body): StreamInterface => $messages->createStream($body),
            );

            $response = (new JsonThrowableResponseFactory($messages, $bodyFactory, true))
                ->createResponseFromThrowable($throwable)
            ;
            $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($payload);
            $exception = $payload['exception'];
            self::assertIsArray($exception);
            $trace = $exception['trace'];
            self::assertIsArray($trace);
            self::assertArrayHasKey(0, $trace);
            $firstFrame = $trace[0];
            self::assertIsArray($firstFrame);

            self::assertSame(503, $response->getStatusCode());
            self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('Service Unavailable', $response->getReasonPhrase());
            self::assertSame('Request failed', $payload['error']);
            self::assertSame(\RuntimeException::class, $exception['class']);
            self::assertSame($throwable->getMessage(), $exception['message']);
            self::assertSame($throwable->getFile(), $exception['file']);
            self::assertSame($throwable->getLine(), $exception['line']);
            self::assertSame('createDiagnosticThrowable', $firstFrame['function']);
            self::assertSame(self::class, $firstFrame['class']);
            self::assertSame('->', $firstFrame['type']);
            self::assertCount(2, $payload);
            self::assertCount(5, $exception);
            self::assertCount(\count($throwable->getTrace()), $trace);
            foreach ($trace as $frame) {
                self::assertIsArray($frame);
                self::assertSame([], array_diff(array_keys($frame), ['file', 'line', 'class', 'type', 'function']));
            }
            self::assertStringNotContainsString('Private previous cause', (string) $response->getBody());
            self::assertStringNotContainsString($marker, (string) $response->getBody());
            self::assertSame($nativeFrame, $throwable->getTrace()[0]);
        } finally {
            ini_set('zend.exception_ignore_args', (string) $previous);
        }
    }

    /** Native invalid strings are substituted without serializing the Throwable object. */
    public function testDebugSubstitutesInvalidUtf8InMessageAndFilename(): void
    {
        $throwable = new class extends \RuntimeException implements \JsonSerializable {
            /** Supplies native throwable metadata containing real invalid UTF-8 bytes. */
            public function __construct()
            {
                parent::__construct("Private \xFF diagnostic", 404);

                $this->file = "source-\xFF.php";
                $this->line = 42;
            }

            /**
             * Detects accidental generic object serialization instead of explicit metadata.
             *
             * @return array<string, string> no data is serialized through this object contract
             *
             * @throws \LogicException if the Throwable itself is serialized
             */
            public function jsonSerialize(): array
            {
                throw new \LogicException('The Throwable must not be serialized.');
            }
        };
        $messages = new Psr17Factory();

        $response = (new JsonThrowableResponseFactory($messages, $messages, true))
            ->createResponseFromThrowable($throwable)
        ;
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        $exception = $payload['exception'];
        self::assertIsArray($exception);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not Found', $response->getReasonPhrase());
        self::assertSame(\get_class($throwable), $exception['class']);
        self::assertSame("Private \u{FFFD} diagnostic", $exception['message']);
        self::assertSame("source-\u{FFFD}.php", $exception['file']);
        self::assertSame(42, $exception['line']);
        self::assertSame("Private \xFF diagnostic", $throwable->getMessage());
        self::assertSame("source-\xFF.php", $throwable->getFile());
    }

    /**
     * Creates a real native stack frame with a private application argument.
     *
     * @param object $argument the object that may occur in trace args but never in the response
     * @param string $marker   the private scalar argument excluded from the representation
     *
     * @return \RuntimeException the diagnostic event with native metadata and a private previous cause
     */
    private function createDiagnosticThrowable(object $argument, string $marker): \RuntimeException
    {
        return new \RuntimeException(
            'Synthetic development diagnostic',
            503,
            new \RuntimeException('Private previous cause'),
        );
    }
}
