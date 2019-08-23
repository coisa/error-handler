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

namespace CoiSA\ErrorHandler\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Http\RequestHandler\ThrowableRequestHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerMiddleware
 *
 * @package CoiSA\ErrorHandler\Middleware
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    /**
     * @var ErrorHandlerInterface
     */
    private $errorHandler;

    /**
     * @var ThrowableRequestHandler
     */
    private $throwableHandlerHandler;

    /**
     * ErrorHandlerMiddleware constructor.
     *
     * @param ErrorHandlerInterface   $errorHandler
     * @param ThrowableRequestHandler $throwableHandlerHandler
     */
    public function __construct(
        ErrorHandlerInterface $errorHandler,
        ThrowableRequestHandler $throwableHandlerHandler
    ) {
        $this->errorHandler            = $errorHandler;
        $this->throwableHandlerHandler = $throwableHandlerHandler;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @throws \Throwable
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->errorHandler->register();

        try {
            $response = $this->throwableHandlerHandler->process($request, $handler);
        } catch (\Throwable $throwable) {
            $errorRequest = $request->withAttribute(
                ThrowableRequestHandler::class,
                $throwable
            );

            $response = $this->throwableHandlerHandler->handle($errorRequest);
        } finally {
            $this->errorHandler->unregister();
        }

        return $response;
    }
}
