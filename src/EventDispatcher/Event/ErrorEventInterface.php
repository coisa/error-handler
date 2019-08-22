<?php

namespace CoiSA\ErrorHandler\EventDispatcher\Event;

use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Interface ErrorEventInterface
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Event
 */
interface ErrorEventInterface
{
    /**
     * @return \Throwable
     */
    public function getTrowable(): \Throwable;

    /**
     * @return string
     */
    public function __toString(): string;
}
