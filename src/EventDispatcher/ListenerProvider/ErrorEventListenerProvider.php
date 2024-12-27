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

namespace CoiSA\ErrorHandler\EventDispatcher\ListenerProvider;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ErrorEventListenerProvider
 *
 * Provides listeners for `ErrorEvent` objects.
 * This class SHALL ensure that only valid listeners are returned
 * when an event of type `ErrorEvent` is dispatched.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\ListenerProvider
 */
final class ErrorEventListenerProvider implements ListenerProviderInterface
{
    /**
     * @var callable[] The list of registered listeners.
     */
    private array $listeners;

    /**
     * Constructs an ErrorEventListenerProvider instance.
     *
     * @param callable ...$listeners A variadic list of listeners to handle error events.
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
     * @param callable $listener The listener to attach.
     *
     * @return void
     */
    public function attach(callable $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Retrieves listeners for a given event.
     *
     * This method SHALL return listeners only if the event is an instance of `ErrorEvent`.
     *
     * @param object $event The event for which listeners are requested.
     *
     * @return iterable The list of applicable listeners.
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
