<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\EventDispatcher\ListenerProvider;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ThrowableListenerProvider.
 *
 * Provides listeners specifically for throwable events.
 * This class SHALL ensure that only valid listeners for `\Throwable` events
 * are returned when an event is dispatched.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\ListenerProvider
 */
final class ThrowableListenerProvider implements ListenerProviderInterface
{
    /**
     * @var callable[] list of registered listeners for throwable events
     */
    private array $listeners;

    /**
     * Constructs the ThrowableListenerProvider.
     *
     * @param callable ...$listeners A variadic list of listeners for throwable events.
     */
    public function __construct(callable ...$listeners)
    {
        $this->listeners = $listeners;
    }

    /**
     * Attaches a listener to the provider.
     *
     * This method SHALL add a new callable listener to the internal list.
     *
     * @param callable $listener the listener to attach
     */
    public function attach(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Retrieves listeners for a given event.
     *
     * This method SHALL return listeners only if the event is an instance of `\Throwable`.
     *
     * @param object $event the event for which listeners are requested
     *
     * @return iterable<callable> the list of applicable listeners
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
