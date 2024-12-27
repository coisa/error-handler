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

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchErrorEventThrowableHandler
 *
 * Handles throwable instances by dispatching them as error events through an event dispatcher.
 * This class SHALL ensure that every throwable is wrapped in an ErrorEvent and dispatched
 * appropriately using the provided EventDispatcherInterface.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class DispatchErrorEventThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * @var EventDispatcherInterface The event dispatcher used to dispatch error events.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Constructs a DispatchErrorEventThrowableHandler instance.
     *
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher instance used for dispatching error events.
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handles a throwable instance.
     *
     * This method SHALL wrap the throwable in an ErrorEvent instance
     * and dispatch it using the provided event dispatcher.
     *
     * @param \Throwable $throwable The throwable instance to handle.
     *
     * @return void
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $this->eventDispatcher->dispatch(new ErrorEvent($throwable));
    }
}
