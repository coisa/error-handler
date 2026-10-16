<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Handler;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Reports a throwable directly to an application-owned PSR-3 logger.
 *
 * Logger failures propagate; this adapter does not register callbacks or render output.
 */
final class LogThrowableHandler implements ThrowableHandlerInterface
{
    /** @var LoggerInterface the application logger receiving each report */
    private LoggerInterface $logger;

    /** @var string the log level forwarded without severity inference or validation */
    private string $logLevel;

    /** @var array<array-key, mixed> caller context preserved except for the reserved exception key */
    private array $context;

    /**
     * Configures direct logging without discovering or registering other services.
     *
     * @param LoggerInterface         $logger   the application-owned diagnostic sink
     * @param string                  $logLevel the level interpreted by that logger
     * @param array<array-key, mixed> $context  additional context; the reported throwable overrides exception
     */
    public function __construct(LoggerInterface $logger, string $logLevel = LogLevel::ERROR, array $context = [])
    {
        $this->logger = $logger;
        $this->logLevel = $logLevel;
        $this->context = $context;
    }

    /**
     * Logs the exact message and original throwable once, including an empty message.
     *
     * Context values are not formatted or coerced; their key order is not a contract.
     *
     * @param \Throwable $throwable the original failure to include under exception
     *
     * @throws \Throwable the identical failure raised by the logger
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $this->logger->log(
            $this->logLevel,
            $throwable->getMessage(),
            ['exception' => $throwable] + $this->context,
        );
    }
}
