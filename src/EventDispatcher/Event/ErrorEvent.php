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

namespace CoiSA\ErrorHandler\EventDispatcher\Event;

/**
 * Class ErrorEvent
 *
 * Represents an event that encapsulates a throwable exception.
 * This class SHALL be used to propagate error-related events through the application.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Event
 */
final class ErrorEvent implements ErrorEventInterface
{
    /**
     * @var \Throwable The throwable instance associated with the event.
     */
    private \Throwable $throwable;

    /**
     * Constructs the ErrorEvent.
     *
     * @param \Throwable $throwable The throwable instance representing the error.
     */
    public function __construct(\Throwable $throwable)
    {
        $this->throwable = $throwable;
    }

    /**
     * Returns the string representation of the throwable.
     *
     * This method SHALL return the string representation of the throwable,
     * typically used for debugging or logging purposes.
     *
     * @return string The string representation of the throwable.
     */
    public function __toString(): string
    {
        return (string) $this->getThrowable();
    }

    /**
     * Retrieves the throwable instance.
     *
     * This method SHALL return the throwable instance associated with the event.
     *
     * @return \Throwable The throwable instance.
     */
    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}
