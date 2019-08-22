<?php

/**
 * Class CallableExceptionHandler
 */
final class CallableExceptionHandler implements ExceptionHandlerInterface
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
     * @param Throwable $throwable
     */
    public function handleException(\Throwable $throwable): void
    {
        ($this->handler)($throwable);
    }
}
