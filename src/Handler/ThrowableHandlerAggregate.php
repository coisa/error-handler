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

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class ThrowableHandlerAggregate
 *
 * Aggregates multiple throwable handlers and delegates the processing
 * of throwable instances to each handler in sequence.
 *
 * This class SHALL ensure that each handler is called with the same
 * throwable instance, in the order they were attached.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class ThrowableHandlerAggregate implements ThrowableHandlerInterface
{
    /**
     * @var ThrowableHandlerInterface[] List of throwable handlers.
     */
    private array $handlers;

    /**
     * Constructs the ThrowableHandlerAggregate.
     *
     * @param ThrowableHandlerInterface ...$throwableHandlers A list of throwable handlers.
     */
    public function __construct(ThrowableHandlerInterface ...$throwableHandlers)
    {
        $this->handlers = $throwableHandlers;
    }

    /**
     * Attaches a new throwable handler to the aggregate.
     *
     * This method SHALL add the provided handler to the list of handlers.
     *
     * @param ThrowableHandlerInterface $throwableHandler The handler to attach.
     *
     * @return void
     */
    public function attach(ThrowableHandlerInterface $throwableHandler): void
    {
        $this->handlers[] = $throwableHandler;
    }

    /**
     * Handles a throwable instance.
     *
     * This method SHALL delegate the throwable handling to each attached handler,
     * in the order they were attached.
     *
     * @param \Throwable $throwable The throwable instance to handle.
     *
     * @return void
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handleThrowable($throwable);
        }
    }
}
