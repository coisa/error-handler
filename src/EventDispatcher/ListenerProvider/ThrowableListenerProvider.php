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

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ThrowableListenerProvider
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\ListenerProvider
 */
final class ThrowableListenerProvider implements ListenerProviderInterface
{
    /**
     * @var callable[]
     */
    private $listeners;

    /**
     * ThrowableListenerProvider constructor.
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
        if (!$event instanceof \Throwable) {
            return [];
        }

        foreach ($this->listeners as $listener) {
            yield $listener;
        }
    }
}
