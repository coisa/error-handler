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

namespace CoiSA\ErrorHandler\Exception;

/**
 * Preserves the original diagnostic and every failure from one reporting pass.
 *
 * The fixed message does not include diagnostic details. The original cause and
 * reporter failures remain private diagnostic data, not a safe HTTP response body.
 */
final class AggregateReportingException extends \RuntimeException
{
    /** @var \Throwable the identical diagnostic originally supplied to the reporters */
    private \Throwable $originalThrowable;

    /** @var non-empty-list<\Throwable> the identical reporter failures in delivery order */
    private array $failures;

    /**
     * Retain at least one reporter failure and use the original diagnostic as the previous cause.
     *
     * @param \Throwable $originalThrowable the original diagnostic, not a replacement reporter failure
     * @param \Throwable $failure           the first reporter failure in delivery order
     * @param \Throwable ...$failures       the remaining reporter failures in delivery order
     */
    public function __construct(\Throwable $originalThrowable, \Throwable $failure, \Throwable ...$failures)
    {
        $this->originalThrowable = $originalThrowable;
        $this->failures = [$failure, ...array_values($failures)];

        parent::__construct('One or more throwable handlers failed.', 0, $originalThrowable);
    }

    /**
     * Return the original diagnostic without cloning or replacing it.
     *
     * @return \Throwable the identical event supplied to every reporter slot
     */
    public function getOriginalThrowable(): \Throwable
    {
        return $this->originalThrowable;
    }

    /**
     * Return the ordered reporter failures without deduplicating repeated objects.
     *
     * @return non-empty-list<\Throwable> the identical failures from this reporting pass
     */
    public function getFailures(): array
    {
        return $this->failures;
    }
}
