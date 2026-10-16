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

use CoiSA\ErrorHandler\Exception\AggregateReportingException;
use CoiSA\ErrorHandler\Handler\BestEffortThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$original = new RuntimeException('Synthetic operation failure');
$auditFailure = new RuntimeException('Synthetic audit failure');
$backupFailure = new Error('Synthetic backup failure');
$attempts = [];

$audit = new CallableThrowableHandler(
    /**
     * Record the first attempt and fail with a distinct synthetic exception.
     *
     * @param Throwable $event the original diagnostic to report
     *
     * @throws RuntimeException the exact synthetic audit failure
     */
    static function (Throwable $event) use (&$attempts, $auditFailure): void {
        $attempts[] = ['audit', $event];

        throw $auditFailure;
    },
);

$archive = new CallableThrowableHandler(
    /**
     * Record the successful middle attempt without an external transport.
     *
     * @param Throwable $event the unchanged original diagnostic
     */
    static function (Throwable $event) use (&$attempts): void {
        $attempts[] = ['archive', $event];
    },
);

$backup = new CallableThrowableHandler(
    /**
     * Record the final attempt and fail with a distinct synthetic Error.
     *
     * @param Throwable $event the unchanged original diagnostic
     *
     * @throws Error the exact synthetic backup failure
     */
    static function (Throwable $event) use (&$attempts, $backupFailure): void {
        $attempts[] = ['backup', $event];

        throw $backupFailure;
    },
);

$reporter = new BestEffortThrowableHandlerAggregate($audit, $archive, $backup);
$recordedFailure = null;

try {
    $reporter->handleThrowable($original);
} catch (AggregateReportingException $failure) {
    $recordedFailure = [
        'original' => $failure->getOriginalThrowable(),
        'failures' => $failure->getFailures(),
    ];
}

exampleCheck([
    ['audit', $original],
    ['archive', $original],
    ['backup', $original],
] === $attempts, 'Every reporter must receive the same original once, in order.');

exampleCheck([
    'original' => $original,
    'failures' => [$auditFailure, $backupFailure],
] === $recordedFailure, 'Partial success must preserve the original and every reporter failure.');

echo 'best-effort order, original cause and all reporter failures passed' . PHP_EOL;
