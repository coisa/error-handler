<?php

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class CallableThrowableHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class CallableThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * CallableExceptionHandler constructor.
     *
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        ($this->handler)($throwable);
    }
}
