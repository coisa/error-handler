<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Functional\Container;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\Http\Handler\CallableHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\ResponseFactory;
use Zend\Diactoros\ServerRequest;
use Zend\Diactoros\StreamFactory;

/**
 * Class ErrorHandlerMiddlewareTest
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
 */
final class ErrorHandlerMiddlewareTest extends TestCase
{
    public function testErrorHandlerMiddlewareHandleRequestException(): void
    {
        $exception = new \InvalidArgumentException(\uniqid('test', true), \random_int(400, 500));

        $callableThrowableHandler = new CallableThrowableHandler(function (\Throwable $throwable): void {
            echo \json_encode([
                'code'    => $throwable->getCode(),
                'message' => $throwable->getMessage(),
            ]);
        });

        $streamFactory  = new StreamFactory();
        $reponseFactory = new ResponseFactory();
        $serverRequest  = new ServerRequest();

        $requestHandler = new CallableHandler(function () use ($exception): void {
            throw $exception;
        });

        $errorHandler = new ErrorHandler($callableThrowableHandler);

        $throwableStreamFactory   = new ThrowableStreamFactory($streamFactory, $callableThrowableHandler);
        $throwableResponseFactory = new ThrowableResponseFactory($throwableStreamFactory, $reponseFactory);

        $middleware = new ErrorHandlerMiddleware($errorHandler, $throwableResponseFactory);
        $response   = $middleware->process($serverRequest, $requestHandler);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame($exception->getCode(), $response->getStatusCode());

        $body = \json_decode((string) $response->getBody(), true);

        $this->assertIsArray($body);
        $this->assertSame($exception->getCode(), $body['code']);
        $this->assertSame($exception->getMessage(), $body['message']);
    }
}
