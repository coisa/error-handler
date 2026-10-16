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

/**
 * Interface ThrowableHandlerInterface.
 *
 * Defines the contract for handling throwable instances.
 * Implementing classes provide a mechanism to process Throwable instances.
 * Collaborator failures may propagate; this interface does not guarantee delivery
 * or require implementations to suppress failures, retry or render a response.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface ThrowableHandlerInterface
{
    /**
     * Handles a throwable instance.
     *
     * Processes the provided throwable according to the implementation's reporting policy.
     * Returning normally does not establish transport delivery or process recovery.
     *
     * @param \Throwable $throwable the throwable instance to handle
     *
     * @throws \Throwable if the configured reporting collaborator fails
     */
    public function handleThrowable(\Throwable $throwable): void;
}
