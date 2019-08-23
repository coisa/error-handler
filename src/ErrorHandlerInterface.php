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

namespace CoiSA\ErrorHandler;

/**
 * Interface ErrorHandlerInterface
 *
 * @package CoiSA\ErrorHandler
 */
interface ErrorHandlerInterface
{
    /**
     * Register error-handler
     */
    public function register(): void;

    /**
     * Unregister error-handler
     */
    public function unregister(): void;
}
