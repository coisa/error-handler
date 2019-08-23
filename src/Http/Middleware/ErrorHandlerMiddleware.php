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
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
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
     * @var ThrowableResponseFactoryInterface
     */
    private $throwableResponseFactory;

    /**
     * ErrorHandlerMiddleware constructor.
     *
     * @param ErrorHandlerInterface             $errorHandler
     * @param ThrowableResponseFactoryInterface $throwableResponseFactory
     */
    public function __construct(
        ErrorHandlerInterface $errorHandler,
        ThrowableResponseFactoryInterface $throwableResponseFactory
    ) {
        $this->errorHandler             = $errorHandler;
        $this->throwableResponseFactory = $throwableResponseFactory;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->errorHandler->register();

        try {
            return $handler->handle($request);
        } catch (\Throwable $throwable) {
            return $this->throwableResponseFactory->createResponseFromThrowable($throwable);
        } finally {
            $this->errorHandler->unregister();
        }
    }
}
