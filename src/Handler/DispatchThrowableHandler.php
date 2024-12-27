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

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchThrowableHandler
 *
 * Handles throwable instances by dispatching them through an event dispatcher.
 * This class SHALL ensure that each throwable is passed to the EventDispatcherInterface
 * for further processing or logging.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class DispatchThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * @var EventDispatcherInterface The event dispatcher used for dispatching throwables.
     */
    private EventDispatcherInterface $eventDispatcher;

    /**
     * Constructs a DispatchThrowableHandler instance.
     *
     * @param EventDispatcherInterface $eventDispatcher The event dispatcher instance used for dispatching throwables.
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Handles a throwable instance.
     *
     * This method SHALL dispatch the provided throwable using the event dispatcher.
     *
     * @param \Throwable $throwable The throwable instance to handle.
     *
     * @return void
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $this->eventDispatcher->dispatch($throwable);
    }
}
