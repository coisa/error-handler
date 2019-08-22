<?php

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ErrorHandlerMiddleware
 */
final class ErrorHandlerMiddleware implements MiddlewareInterface, ExceptionHandlerInterface
{
    public function __construct(
        RequestHandlerInterface $handler
    ) {
        $this->handler = $handler;
    }

    /**
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        \set_error_handler($this->getClosure());
        \set_exception_handler([$this, 'handleException']);

        $response = $handler->handle($request);

        \restore_error_handler();

        return $response;
    }

    public function handleException(\Throwable $throwable): void
    {
        $exceptionHandler = new CallableExceptionHandler(function () use ($throwable) {
            // @FIXME WIP
        });
    }

    /**
     * @return Closure
     */
    private function getClosure(): Closure
    {
        return function (int $errno, string $errstr, string $errfile, int $errline) {
            if (error_reporting() & $errno) {
                throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
            }
        };
    }
}
