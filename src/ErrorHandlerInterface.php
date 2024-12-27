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

namespace CoiSA\ErrorHandler;

/**
 * Interface ErrorHandlerInterface
 *
 * Defines the contract for registering and unregistering error handlers.
 * Classes implementing this interface MUST provide mechanisms to handle
 * the registration and unregistration of error, exception, and shutdown handlers.
 *
 * @package CoiSA\ErrorHandler
 */
interface ErrorHandlerInterface
{
    /**
     * Registers the error handler.
     *
     * Implementing classes MUST ensure that this method sets up error,
     * exception, and shutdown handlers. Multiple calls to this method
     * SHOULD NOT result in duplicate registrations.
     *
     * @return void
     */
    public function register(): void;

    /**
     * Unregisters the error handler.
     *
     * Implementing classes MUST ensure that this method restores
     * previous error, exception, and shutdown handlers. If the handler
     * was never registered, this method SHOULD do nothing.
     *
     * @return void
     */
    public function unregister(): void;
}
