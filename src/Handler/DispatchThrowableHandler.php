<?php

namespace CoiSA\ErrorHandler\Handler;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Class DispatchThrowableHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class DispatchThrowableHandler implements ThrowableHandlerInterface
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
        $this->eventDispatcher->dispatch($throwable);
    }
}
