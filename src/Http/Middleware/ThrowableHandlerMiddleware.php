<?php

namespace CoiSA\ErrorHandler\Http\Middleware;

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerMiddleware
 *
 * @package CoiSA\ErrorHandler\Middleware
 */
final class ThrowableHandlerMiddleware implements MiddlewareInterface
{
    /**
     * @var ThrowableHandlerInterface
     */
    private $throwableHandler;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * ThrowableHandlerMiddleware constructor.
     *
     * @param ThrowableHandlerInterface $throwableHandler
     * @param ResponseFactoryInterface $responseFactory
     * @param StreamFactoryInterface $streamFactory
     */
    public function __construct(
        ThrowableHandlerInterface $throwableHandler,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->throwableHandler = $throwableHandler;
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     * @throws \Throwable
     *
     * @TODO transform throwable code to HTTP code
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (\Throwable $throwable) {
            $body = $this->createStreamFromThrowable($throwable);

            return $this->responseFactory->createResponse(
                $throwable->getCode(),
                $throwable->getMessage()
            )->withBody($body);
        }
    }

    /**
     * @param \Throwable $throwable
     *
     * @return StreamInterface
     */
    private function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        ob_start();
        $this->throwableHandler->handleThrowable($throwable);
        $buffer = ob_get_clean();

        return $this->streamFactory->createStream($buffer);
    }
}
