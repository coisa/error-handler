<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\Test\Functional\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies reporting and public HTTP rendering remain separate operations.
 *
 * @internal
 */
final class ErrorHandlerMiddlewareTest extends TestCase
{
    /**
     * Verifies reporting precedes pure JSON rendering, without captured output or private details.
     */
    public function testReportsOnceBeforeRenderingTheResponse(): void
    {
        $failure = new \RuntimeException('Private database credentials', 503);
        $events = new \ArrayObject();
        $received = [];
        $reporter = new CallableThrowableHandler(
            static function (\Throwable $throwable) use ($events, &$received): void {
                $events[] = 'report';
                $received[] = $throwable;
            }
        );
        $psr17 = new Psr17Factory();
        $streams = new class ($psr17, $events) implements ThrowableStreamFactoryInterface {
            /**
             * Stores the public stream factory and the observable operation order.
             *
             * @param StreamFactoryInterface $streams Creates public body streams.
             * @param \ArrayObject<int, string> $events Receives ordered fixture operations.
             */
            public function __construct(private StreamFactoryInterface $streams, private \ArrayObject $events)
            {
            }

            /**
             * Returns a JSON stream directly without invoking a reporter or writing output.
             *
             * @param \Throwable $throwable The private failure whose details remain undisclosed.
             *
             * @return StreamInterface The application's controlled public JSON body.
             */
            public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
            {
                $this->events[] = 'render';

                return $this->streams->createStream('{"error":"Service unavailable"}');
            }
        };
        $next = self::downstream($failure);
        $middleware = new ErrorHandlerMiddleware(
            new ErrorHandler($reporter),
            new ThrowableResponseFactory($streams, $psr17)
        );
        ob_start();
        try {
            $response = $middleware->process($psr17->createServerRequest('GET', '/'), $next);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        self::assertSame('', $output);
        self::assertSame(['report', 'render'], $events->getArrayCopy());
        self::assertSame([$failure], $received);
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('{"error":"Service unavailable"}', (string) $response->getBody());
    }

    /**
     * Verifies successful responses pass through without reporting or rendering.
     */
    public function testPassesThroughSuccessfulResponses(): void
    {
        $psr17 = new Psr17Factory();
        $expected = $psr17->createResponse(204);
        $reports = 0;
        $middleware = new ErrorHandlerMiddleware(
            new ErrorHandler(new CallableThrowableHandler(static function () use (&$reports): void {
                ++$reports;
            })),
            new ThrowableResponseFactory(new GenericThrowableStreamFactory($psr17), $psr17)
        );
        $next = new class ($expected) implements RequestHandlerInterface {
            /**
             * Stores the application's successful response.
             *
             * @param ResponseInterface $response The response that must pass through unchanged.
             */
            public function __construct(private ResponseInterface $response)
            {
            }

            /**
             * Returns the application's successful response.
             *
             * @param ServerRequestInterface $request The synthetic request.
             *
             * @return ResponseInterface The unchanged downstream response.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->response;
            }
        };

        self::assertSame($expected, $middleware->process($psr17->createServerRequest('GET', '/'), $next));
        self::assertSame(0, $reports);
    }

    /**
     * Creates the failing application used by the rendering regression.
     *
     * @param \Throwable $failure The exact failure to report and render.
     *
     * @return RequestHandlerInterface The synthetic downstream application.
     */
    private static function downstream(\Throwable $failure): RequestHandlerInterface
    {
        return new class ($failure) implements RequestHandlerInterface {
            /**
             * Stores the downstream failure.
             *
             * @param \Throwable $failure The exact throwable to propagate.
             */
            public function __construct(private \Throwable $failure)
            {
            }

            /**
             * Throws the private failure for the middleware to handle.
             *
             * @param ServerRequestInterface $request The synthetic incoming request.
             *
             * @return ResponseInterface The error response supplied by middleware.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };
    }
}
