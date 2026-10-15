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

namespace CoiSA\ErrorHandler\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerMiddleware
 *
 * Middleware responsible for capturing and processing throwables
 * that occur during request processing.
 *
 * This middleware SHALL use an ErrorHandlerInterface to register
 * and unregister error handlers and SHALL utilize a ThrowableResponseFactoryInterface
 * to generate appropriate HTTP responses for captured throwables.
 * The scoped handler MUST also report throwables. Reporting runs before the
 * response factory; renderers MUST NOT invoke the diagnostic reporter again.
 *
 * @package CoiSA\ErrorHandler\Http\Middleware
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface
{
    /**
     * @var ErrorHandlerInterface&ThrowableHandlerInterface The lifecycle and reporter sharing one failure guard.
     */
    private ErrorHandlerInterface&ThrowableHandlerInterface $errorHandler;

    /**
     * @var ThrowableResponseFactoryInterface Factory for creating HTTP responses from throwables.
     */
    private ThrowableResponseFactoryInterface $throwableResponseFactory;

    /**
     * Constructs the ErrorHandlerMiddleware.
     *
     * @param ErrorHandlerInterface&ThrowableHandlerInterface $errorHandler The scoped lifecycle and reporter.
     * @param ThrowableResponseFactoryInterface $throwableResponseFactory Factory for creating throwable responses.
     */
    public function __construct(
        ErrorHandlerInterface&ThrowableHandlerInterface $errorHandler,
        ThrowableResponseFactoryInterface $throwableResponseFactory
    ) {
        $this->errorHandler = $errorHandler;
        $this->throwableResponseFactory = $throwableResponseFactory;
    }

    /**
     * Processes an incoming server request.
     *
     * This method SHALL register the error handler, delegate the request to
     * the next middleware, and handle any thrown exceptions by generating
     * an appropriate HTTP response using the ThrowableResponseFactory.
     *
     * @param ServerRequestInterface $request The incoming server request.
     * @param RequestHandlerInterface $handler The next request handler in the chain.
     *
     * @return ResponseInterface The generated HTTP response.
     *
     * @throws \Throwable If reporting or rendering fails; callbacks are restored before propagation.
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $this->errorHandler->register();

        try {
            $response = $handler->handle($request);
        } catch (\Throwable $throwable) {
            $this->errorHandler->handleThrowable($throwable);
            $response = $this->throwableResponseFactory->createResponseFromThrowable($throwable);
        } finally {
            $this->errorHandler->unregister();
        }

        return $response;
    }
}
