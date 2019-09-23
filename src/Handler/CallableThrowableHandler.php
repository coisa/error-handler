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
