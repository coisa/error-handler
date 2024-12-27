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

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface ThrowableHandlerInterface
 *
 * Defines the contract for handling throwable instances.
 * Implementing classes SHALL provide a mechanism to process Throwable instances
 * without raising additional exceptions during execution.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface ThrowableHandlerInterface
{
    /**
     * Handles a throwable instance.
     *
     * Implementing classes SHALL process the provided throwable
     * and MUST NOT throw exceptions during this operation.
     *
     * @param \Throwable $throwable The throwable instance to handle.
     *
     * @return void
     */
    public function handleThrowable(\Throwable $throwable): void;
}
