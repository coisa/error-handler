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

namespace CoiSA\ErrorHandler\EventDispatcher\Listener;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;

/**
 * Class ErrorEventCallableListener
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class ErrorEventCallableListener
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * ErrorEventCallableListener constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @param ErrorEventInterface $errorEvent
     *
     * @return ErrorEventInterface
     */
    public function __invoke(ErrorEventInterface $errorEvent): ErrorEventInterface
    {
        ($this->callable)($errorEvent);

        return $errorEvent;
    }
}
