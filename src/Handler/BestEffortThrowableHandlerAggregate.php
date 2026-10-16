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

use CoiSA\ErrorHandler\Exception\AggregateReportingException;

/**
 * Attempts every configured reporter before exposing their ordered failures.
 *
 * Each constructor slot receives the same original throwable once per invocation.
 * Repeated slots are separate attempts; this aggregate adds no retries or recovery.
 * Process termination or an uncatchable engine failure can prevent later attempts.
 */
final class BestEffortThrowableHandlerAggregate implements ThrowableHandlerInterface
{
    /** @var list<ThrowableHandlerInterface> the fixed reporter slots in delivery order */
    private array $handlers;

    /**
     * Store the explicit reporter list without invoking reporters or registering callbacks.
     *
     * @param ThrowableHandlerInterface ...$handlers the ordered reporter slots, including deliberate duplicates
     */
    public function __construct(ThrowableHandlerInterface ...$handlers)
    {
        $this->handlers = array_values($handlers);
    }

    /**
     * Deliver once to every slot and aggregate any failures after the complete pass.
     *
     * Zero reporters and all-success passes return normally. Any reporter failure,
     * including partial success, produces one aggregate exception with every failure.
     *
     * @param \Throwable $throwable the unchanged original diagnostic event
     *
     * @throws AggregateReportingException after all attempts when at least one reporter failed
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $failures = [];

        foreach ($this->handlers as $handler) {
            try {
                $handler->handleThrowable($throwable);
            } catch (\Throwable $failure) {
                $failures[] = $failure;
            }
        }

        if ([] !== $failures) {
            throw new AggregateReportingException($throwable, ...$failures);
        }
    }
}
