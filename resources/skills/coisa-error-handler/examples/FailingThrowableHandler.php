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

namespace CoiSA\ErrorHandler\Example;

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;

/**
 * Exposes a deterministic collaborator failure without touching real transports.
 */
final class FailingThrowableHandler implements ThrowableHandlerInterface
{
    /** @var \Throwable the exact failure to propagate */
    private \Throwable $failure;

    /**
     * Retain a synthetic reporting failure.
     *
     * @param \Throwable $failure the original failure object
     */
    public function __construct(\Throwable $failure)
    {
        $this->failure = $failure;
    }

    /**
     * Fail when delivery is attempted.
     *
     * @param \Throwable $throwable the original diagnostic event
     *
     * @throws \Throwable the identical configured reporting failure
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        throw $this->failure;
    }
}
