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
 * Retains synthetic diagnostics without output, files or an external transport.
 */
final class RecordingThrowableHandler implements ThrowableHandlerInterface
{
    /** @var list<\Throwable> original failures in delivery order */
    private array $throwables = [];

    /**
     * Record the same throwable object once for each explicit invocation.
     *
     * @param \Throwable $throwable the original diagnostic event
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $this->throwables[] = $throwable;
    }

    /**
     * Read the observed synthetic deliveries.
     *
     * @return list<\Throwable> original throwable objects
     */
    public function getThrowables(): array
    {
        return $this->throwables;
    }
}
