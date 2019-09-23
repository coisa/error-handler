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

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchErrorEventThrowableHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class DispatchErrorEventThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * DispatchEventThrowableHandler constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     */
    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param \Throwable $throwable
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $event = new ErrorEvent($throwable);

        $this->eventDispatcher->dispatch($event);
    }
}
