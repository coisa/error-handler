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
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LogErrorEventListener
 *
 * Listens to error events and logs them using the provided PSR-3 logger.
 * This listener MUST handle error events consistently and log them
 * with the appropriate log level.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class LogErrorEventListener
{
    /**
     * @var LoggerInterface PSR-3 compliant logger instance.
     */
    private LoggerInterface $logger;

    /**
     * @var string The log level used when logging the error event.
     */
    private string $logLevel;

    /**
     * Constructs the LogErrorEventListener.
     *
     * @param LoggerInterface $logger A PSR-3 compliant logger instance.
     * @param string $logLevel The log level for the error events. Defaults to LogLevel::ERROR.
     */
    public function __construct(
        LoggerInterface $logger,
        string $logLevel = LogLevel::ERROR
    ) {
        $this->logger = $logger;
        $this->logLevel = $logLevel;
    }

    /**
     * Handles the error event by logging it.
     *
     * This method SHALL log the provided error event using the specified log level.
     *
     * @param ErrorEventInterface $errorEvent The error event to be logged.
     *
     * @return ErrorEventInterface The logged error event.
     */
    public function __invoke(ErrorEventInterface $errorEvent): ErrorEventInterface
    {
        $this->logger->log($this->logLevel, (string) $errorEvent);

        return $errorEvent;
    }
}
