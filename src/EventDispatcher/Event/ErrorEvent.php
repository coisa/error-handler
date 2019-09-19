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

namespace CoiSA\ErrorHandler\EventDispatcher\Event;

/**
 * Class ErrorEvent
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Event
 */
final class ErrorEvent implements ErrorEventInterface
{
    /**
     * @var \Throwable
     */
    private $throwable;

    /**
     * ErrorEvent constructor.
     *
     * @param \Throwable $throwable
     *
     * @TODO Add debug stack back trace
     */
    public function __construct(\Throwable $throwable)
    {
        $this->throwable = $throwable;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getThrowable();
    }

    /**
     * @return \Throwable
     */
    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}
