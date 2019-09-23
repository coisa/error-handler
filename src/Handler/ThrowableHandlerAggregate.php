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
 * Class ThrowableHandlerAggregate
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class ThrowableHandlerAggregate implements ThrowableHandlerInterface
{
    /**
     * @var ThrowableHandlerInterface[]
     */
    private $handlers;

    /**
     * ThrowableHandlerAggregate constructor.
     *
     * @param ThrowableHandlerInterface ...$throwableHandlers
     */
    public function __construct(ThrowableHandlerInterface ...$throwableHandlers)
    {
        $this->handlers = $throwableHandlers;
    }

    /**
     * @param ThrowableHandlerInterface $throwableHandler
     */
    public function attach(ThrowableHandlerInterface $throwableHandler): void
    {
        $this->handlers[] = $throwableHandler;
    }

    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handleThrowable($throwable);
        }
    }
}
