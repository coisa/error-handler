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
 * Interface ShutdownHandlerInterface.
 *
 * Defines the contract for handling shutdown events in PHP applications.
 * Implementing classes MUST provide logic to handle errors and exceptions
 * that occur during the shutdown phase, including fatal errors.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface ShutdownHandlerInterface
{
    /**
     * Handles shutdown events.
     *
     * This method SHALL handle PHP shutdown events, particularly those caused
     * by fatal errors. Implementations MAY log errors, send notifications,
     * or perform cleanup tasks. If appropriate, it MAY throw exceptions to
     * delegate handling to a throwable handler.
     */
    public function handleShutdown(): void;
}
