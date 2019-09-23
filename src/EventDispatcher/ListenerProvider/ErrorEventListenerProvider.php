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

namespace CoiSA\ErrorHandler\EventDispatcher\ListenerProvider;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ErrorEventListenerProvider
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\ListenerProvider
 */
final class ErrorEventListenerProvider implements ListenerProviderInterface
{
    /**
     * @var callable[]
     */
    private $listeners;

    /**
     * ErrorEventListenerProvider constructor.
     *
     * @param callable ...$listeners
     */
    public function __construct(callable ...$listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * @param callable $listener
     */
    public function attach(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * @param object $event
     *
     * @return iterable
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (!$event instanceof ErrorEvent) {
            return [];
        }

        foreach ($this->listeners as $listener) {
            yield $listener;
        }
    }
}
