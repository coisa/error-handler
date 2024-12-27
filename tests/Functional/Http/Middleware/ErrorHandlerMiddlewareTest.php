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

namespace CoiSA\ErrorHandler\Test\Functional\Container;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerMiddlewareTest.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
 *
 * @internal
 * @coversNothing
 */
final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testErrorHandlerMiddlewareHandleRequestException(): void
    {
        $exception = new \InvalidArgumentException(uniqid('test', true), random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            echo json_encode([
                'code'    => $throwable->getCode(),
                'message' => $throwable->getMessage(),
            ]);
        });

        $streamFactory  = $reponseFactory = new Psr17Factory();
        $serverRequest  = $streamFactory->createServerRequest('GET', 'http://localhost');

        $requestHandler = new class ($exception) implements RequestHandlerInterface {
            private $exception;

            public function __construct(\Throwable $exception)
            {
                $this->exception = $exception;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->exception;
            }
        };

        $errorHandler = new ErrorHandler($callableThrowableHandler);

        $throwableStreamFactory   = new ThrowableStreamFactory($streamFactory, $callableThrowableHandler);
        $throwableResponseFactory = new ThrowableResponseFactory($throwableStreamFactory, $reponseFactory);

        $middleware = new ErrorHandlerMiddleware($errorHandler, $throwableResponseFactory);
        $response   = $middleware->process($serverRequest, $requestHandler);

        static::assertInstanceOf(ResponseInterface::class, $response);
        static::assertSame($exception->getCode(), $response->getStatusCode());

        $body = json_decode((string) $response->getBody(), true);

        static::assertIsArray($body);
        static::assertSame($exception->getCode(), $body['code']);
        static::assertSame($exception->getMessage(), $body['message']);
    }
}
