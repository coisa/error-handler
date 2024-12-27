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
 * Interface ErrorEventInterface
 *
 * Defines the contract for error event handling in the error dispatcher system.
 * Implementing classes MUST provide mechanisms to retrieve and represent
 * a throwable instance related to the event.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Event
 */
interface ErrorEventInterface
{
    /**
     * Returns the string representation of the throwable.
     *
     * This method SHALL return a string representation of the associated throwable,
     * typically used for debugging or logging purposes.
     *
     * @return string The string representation of the throwable.
     */
    public function __toString(): string;

    /**
     * Retrieves the throwable instance.
     *
     * This method MUST return the throwable associated with the error event.
     *
     * @return \Throwable The throwable instance related to the event.
     */
    public function getThrowable(): \Throwable;
}
