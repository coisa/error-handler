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

namespace CoiSA\ErrorHandler\EventDispatcher\Listener;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;

/**
 * Class ErrorEventCallableListener
 *
 * Represents a listener that handles error events using a callable.
 * This class MUST be used to execute custom logic whenever an error event is dispatched.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class ErrorEventCallableListener
{
    /**
     * @var callable A callable to handle the error event.
     */
    private $callable;

    /**
     * Constructs the ErrorEventCallableListener.
     *
     * @param callable $callable A callable that processes the error event.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Invokes the error event listener.
     *
     * This method SHALL execute the provided callable with the error event
     * and return the processed event.
     *
     * @param ErrorEventInterface $errorEvent The error event to be processed.
     *
     * @return ErrorEventInterface The processed error event.
     */
    public function __invoke(ErrorEventInterface $errorEvent): ErrorEventInterface
    {
        ($this->callable)($errorEvent);

        return $errorEvent;
    }
}
