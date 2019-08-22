<?php

namespace CoiSA\ErrorHandler\EventDispatcher\Listener;

use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEventInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LogErrorEventListener
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class LogErrorEventListener
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $logLevel;

    /**
     * LogErrorEventListener constructor.
     *
     * @param LoggerInterface $logger
     * @param string $logLevel
     */
    public function __construct(
        LoggerInterface $logger,
        string $logLevel = LogLevel::ERROR
    ) {
        $this->logger = $logger;
        $this->logLevel = $logLevel;
    }

    /**
     * @param ErrorEventInterface $errorEvent
     */
    public function __invoke(ErrorEventInterface $errorEvent)
    {
        $this->logger->log($this->logLevel, (string) $errorEvent);
    }
}
