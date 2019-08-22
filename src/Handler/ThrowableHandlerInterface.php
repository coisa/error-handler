<?php

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface ThrowableHandlerInterface
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface ThrowableHandlerInterface
{
    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable(\Throwable $throwable): void;
}
