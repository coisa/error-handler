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
     * @param string          $logLevel
     */
    public function __construct(
        LoggerInterface $logger,
        string $logLevel = LogLevel::ERROR
    ) {
        $this->logger   = $logger;
        $this->logLevel = $logLevel;
    }

    /**
     * @param ErrorEventInterface $errorEvent
     *
     * @return ErrorEventInterface
     */
    public function __invoke(ErrorEventInterface $errorEvent): ErrorEventInterface
    {
        $this->logger->log($this->logLevel, (string) $errorEvent);

        return $errorEvent;
    }
}
