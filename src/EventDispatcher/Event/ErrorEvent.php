<?php

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
     * @return \Throwable
     */
    public function getTrowable(): \Throwable
    {
        return $this->throwable;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string) $this->getTrowable();
    }
}
