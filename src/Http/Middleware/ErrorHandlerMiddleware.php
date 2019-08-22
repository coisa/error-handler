<?php

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
     * @param ErrorHandlerInterface $errorHandler
     * @param ThrowableRequestHandler $throwableHandlerHandler
     */
    public function __construct(
        ErrorHandlerInterface $errorHandler,
        ThrowableRequestHandler $throwableHandlerHandler
    ) {
        $this->errorHandler = $errorHandler;
        $this->throwableHandlerHandler = $throwableHandlerHandler;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \Throwable
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
