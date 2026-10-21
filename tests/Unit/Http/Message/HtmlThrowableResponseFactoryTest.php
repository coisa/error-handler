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

use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Verifies public HTML, trusted diagnostics and the pure Symfony/PSR-17 boundary.
 *
 * @internal
 */
#[CoversClass(HtmlThrowableResponseFactory::class)]
final class HtmlThrowableResponseFactoryTest extends TestCase
{
    /** Requires both rendering modes to return bytes without emitting them. */
    protected function setUp(): void
    {
        $this->expectOutputString('');
    }

    /**
     * Preserves integer HTTP error codes without disclosing the original diagnostic.
     *
     * @param int|string $code   the native error code or a PDO-style string
     * @param int        $status the library's expected HTTP status
     */
    #[DataProvider('providePublicHtmlPreservesStatusesAndDoesNotDiscloseDiagnosticsCases')]
    public function testPublicHtmlPreservesStatusesAndDoesNotDiscloseDiagnostics(int|string $code, int $status): void
    {
        $failure = new class ($code) extends \PDOException {
            /** @param int|string $code the driver-free native exception code */
            public function __construct(int|string $code)
            {
                parent::__construct('PRIVATE_HTML_DIAGNOSTIC', 0, new \RuntimeException('PRIVATE_PREVIOUS_CAUSE'));
                $this->code = $code;
            }
        };
        $trace = $failure->getTrace();
        $messages = new Psr17Factory();
        $request = (new ServerRequest('GET', 'https://example.test/private'))
            ->withHeader('Accept', 'text/html')
            ->withHeader('X-Debug', '1')
            ->withAttribute('debug', true)
        ;
        $response = (new HtmlThrowableResponseFactory($messages, $messages))
            ->createResponseFromThrowable($failure, $request)
        ;
        $body = (string) $response->getBody();

        self::assertSame($status, $response->getStatusCode());
        self::assertSame($messages->createResponse($status)->getReasonPhrase(), $response->getReasonPhrase());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('X-Debug-Exception'));
        self::assertFalse($response->hasHeader('X-Debug-Exception-File'));
        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('<meta charset="UTF-8"', $body);
        self::assertStringContainsString('The server returned a "' . $status . ' ', $body);
        self::assertStringContainsString('Oops! An Error Occurred', $body);
        self::assertStringNotContainsString('PRIVATE_HTML_DIAGNOSTIC', $body);
        self::assertStringNotContainsString('PRIVATE_PREVIOUS_CAUSE', $body);
        self::assertStringNotContainsString('PDOException', $body);
        self::assertStringNotContainsString($failure->getFile(), $body);
        self::assertSame($trace, $failure->getTrace());
        self::assertSame($code, $failure->getCode());
        self::assertSame('text/html', $request->getHeaderLine('Accept'));
        self::assertTrue($request->getAttribute('debug'));
    }

    /** @return array<string, array{int|string, int}> real HTTP boundaries and type-sensitive fallbacks */
    public static function providePublicHtmlPreservesStatusesAndDoesNotDiscloseDiagnosticsCases(): iterable
    {
        return [
            'below range' => [399, 500],
            'lower boundary' => [400, 400],
            'not found' => [404, 404],
            'nonstandard client status' => [499, 499],
            'server boundary' => [500, 500],
            'service unavailable' => [503, 503],
            'upper boundary' => [599, 599],
            'above range' => [600, 500],
            'default' => [0, 500],
            'negative' => [-1, 500],
            'HTTP-looking string' => ['404', 500],
            'numeric SQLSTATE' => ['23000', 500],
            'alphanumeric SQLSTATE' => ['HY000', 500],
        ];
    }

    /** Diagnostic HTML is a complete document with the original trace and correct displayed status. */
    public function testExplicitDebugIncludesTheOriginalDiagnosticAndTraceInACompleteDocument(): void
    {
        $failure = $this->createDiagnosticThrowable();
        $trace = $failure->getTrace();
        $messages = new Psr17Factory();
        $request = (new ServerRequest('POST', 'https://example.test/debug'))
            ->withHeader('Accept', 'application/json')
            ->withAttribute('debug', false)
        ;

        $response = (new HtmlThrowableResponseFactory($messages, $messages, true))
            ->createResponseFromThrowable($failure, $request)
        ;
        $body = (string) $response->getBody();

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Service Unavailable', $response->getReasonPhrase());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertStringStartsWith('<!DOCTYPE html>', $body);
        self::assertStringContainsString('<html lang="en">', $body);
        self::assertStringContainsString('<meta charset="UTF-8">', $body);
        self::assertStringContainsString('<title>503 Service Unavailable</title>', $body);
        self::assertStringContainsString('<style>', $body);
        self::assertStringContainsString('HTTP 503 <small>Service Unavailable</small>', $body);
        self::assertStringContainsString('PRIVATE_DEBUG_DIAGNOSTIC', $body);
        self::assertStringContainsString('PRIVATE_DEBUG_PREVIOUS', $body);
        self::assertStringContainsString('RuntimeException', $body);
        self::assertStringContainsString('createDiagnosticThrowable', $body);
        self::assertStringContainsString(basename($failure->getFile()), $body);
        self::assertStringEndsWith("</html>\n", $body);
        self::assertSame($trace, $failure->getTrace());
        self::assertSame('application/json', $request->getHeaderLine('Accept'));
        self::assertFalse($request->getAttribute('debug'));
    }

    /**
     * The displayed status must agree with the response even for Symfony request exceptions.
     *
     * @param bool $debug the explicitly selected diagnostic mode
     */
    #[DataProvider('provideModes')]
    public function testSymfonyRequestExceptionDoesNotOverrideTheLibraryStatus(bool $debug): void
    {
        $failure = new BadRequestException('Synthetic request exception', 503);
        $messages = new Psr17Factory();

        $response = (new HtmlThrowableResponseFactory($messages, $messages, $debug))->createResponseFromThrowable(
            $failure,
            new ServerRequest('GET', 'https://example.test/status'),
        );

        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('503 Service Unavailable', str_replace(
            ['<small>', '</small>'],
            '',
            (string) $response->getBody(),
        ));
        self::assertSame(503, $failure->getCode());
    }

    /**
     * Application reason phrases are escaped as HTML text, including embedded line breaks.
     *
     * @param bool $debug the explicitly selected diagnostic mode
     */
    #[DataProvider('provideModes')]
    public function testUsesAndEscapesTheApplicationResponseReasonPhrase(bool $debug): void
    {
        $messages = new Psr17Factory();
        $reason = "Custom <b>public</b> phrase\r\nInjected: no";
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(503)
            ->willReturn($messages->createResponse(503, $reason))
        ;

        $response = (new HtmlThrowableResponseFactory($responses, $messages, $debug))->createResponseFromThrowable(
            new \RuntimeException('Private diagnostic', 503),
            new ServerRequest('GET', 'https://example.test/reason'),
        );
        $body = (string) $response->getBody();

        self::assertSame($reason, $response->getReasonPhrase());
        self::assertStringContainsString("Custom &lt;b&gt;public&lt;/b&gt; phrase\r\nInjected: no", $body);
        self::assertStringNotContainsString('<b>public</b>', $body);
        self::assertFalse($response->hasHeader('Injected'));
    }

    /** Every invocation creates independent response and body objects. */
    public function testRepeatedCallsReturnFreshResponsesAndStreams(): void
    {
        $messages = new Psr17Factory();
        $renderer = new HtmlThrowableResponseFactory($messages, $messages);
        $failure = new \RuntimeException('Private original', 404);
        $request = new ServerRequest('GET', 'https://example.test/repeated');

        $first = $renderer->createResponseFromThrowable($failure, $request);
        $second = $renderer->createResponseFromThrowable($failure, $request);

        self::assertNotSame($first, $second);
        self::assertNotSame($first->getBody(), $second->getBody());
        self::assertSame((string) $first->getBody(), (string) $second->getBody());
    }

    /** A response-factory failure prevents body work and propagates without replacement. */
    public function testPropagatesTheIdenticalResponseFactoryFailure(): void
    {
        $failure = new \LogicException('Response factory failed');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(503)->willThrowException($failure);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $caught = null;

        try {
            (new HtmlThrowableResponseFactory($responses, $streams))->createResponseFromThrowable(
                new \RuntimeException('Original diagnostic', 503),
                new ServerRequest('GET', 'https://example.test/response-failure'),
            );
        } catch (\Throwable $observed) {
            $caught = $observed;
        }

        self::assertSame($failure, $caught);
    }

    /** A body-factory failure propagates after real rendering with no retry. */
    public function testPropagatesTheIdenticalStreamFactoryFailure(): void
    {
        $failure = new \LogicException('Stream factory failed');
        $messages = new Psr17Factory();
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')
            ->with(self::stringContains('<!DOCTYPE html>'))->willThrowException($failure)
        ;
        $caught = null;

        try {
            (new HtmlThrowableResponseFactory($messages, $streams))->createResponseFromThrowable(
                new \RuntimeException('Original diagnostic', 503),
                new ServerRequest('GET', 'https://example.test/stream-failure'),
            );
        } catch (\Throwable $observed) {
            $caught = $observed;
        }

        self::assertSame($failure, $caught);
    }

    /**
     * Rendering leaves caller output bytes and native error/exception callbacks untouched.
     *
     * @param bool $debug the explicitly selected diagnostic mode
     */
    #[DataProvider('provideModes')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRenderingPreservesCallerOutputAndNativeCallbacks(bool $debug): void
    {
        /** @return bool the caller retains ownership of native warning delivery */
        $errorCallback = static fn(): bool => true;

        /**
         * @param \Throwable $event an unexpected native exception
         *
         * @throws \LogicException if the sentinel receives native exception delivery
         */
        $exceptionCallback = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected native exception.', 0, $event);
        };
        set_error_handler($errorCallback);
        set_exception_handler($exceptionCallback);
        $entry = ob_get_level();
        ob_start();
        echo 'caller-owned bytes';

        try {
            $messages = new Psr17Factory();
            (new HtmlThrowableResponseFactory($messages, $messages, $debug))->createResponseFromThrowable(
                new \RuntimeException('Synthetic diagnostic', 503),
                new ServerRequest('GET', 'https://example.test/caller-state'),
            );

            self::assertSame($entry + 1, ob_get_level());
            self::assertSame('caller-owned bytes', ob_get_contents());
            self::assertSame($errorCallback, set_error_handler($errorCallback));
            restore_error_handler();
            self::assertSame($exceptionCallback, set_exception_handler($exceptionCallback));
            restore_exception_handler();
        } finally {
            restore_error_handler();
            restore_exception_handler();
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }
    }

    /** @return array<string, array{bool}> independent public and explicit-debug cases */
    public static function provideModes(): iterable
    {
        return ['public' => [false], 'debug' => [true]];
    }

    /** @return \RuntimeException the original exception with a real named frame and previous cause */
    private function createDiagnosticThrowable(): \RuntimeException
    {
        return new \RuntimeException(
            'PRIVATE_DEBUG_DIAGNOSTIC',
            503,
            new \RuntimeException('PRIVATE_DEBUG_PREVIOUS'),
        );
    }
}
