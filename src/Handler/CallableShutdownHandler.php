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
 * Class CallableShutdownHandler.
 *
 * Handles shutdown processes by delegating execution to a provided callable.
 * This class SHALL ensure that the callable provided during instantiation
 * is executed during the shutdown phase.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class CallableShutdownHandler implements ShutdownHandlerInterface
{
    /**
     * @var callable the callable handler executed during shutdown
     */
    private $handler;

    /**
     * Constructs a CallableShutdownHandler instance.
     *
     * This constructor SHALL accept a callable to be executed during shutdown.
     *
     * @param callable $handler the callable that will handle shutdown events
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Handles the shutdown process.
     *
     * This method SHALL invoke the provided callable handler during shutdown.
     */
    public function handleShutdown(): void
    {
        ($this->handler)();
    }
}
