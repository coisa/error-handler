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

use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;

require __DIR__ . '/bootstrap.php';

$calls = [];
$first = new CallableThrowableHandler(
    /**
     * Record the first configured delivery.
     *
     * @param Throwable $error the synthetic aggregate input
     */
    static function (Throwable $error) use (&$calls): void {
        $calls[] = 'first';
    },
);
$second = new CallableThrowableHandler(
    /**
     * Record an attached delivery after the constructor's reporters.
     *
     * @param Throwable $error the synthetic aggregate input
     */
    static function (Throwable $error) use (&$calls): void {
        $calls[] = 'second';
    },
);
$aggregate = new ThrowableHandlerAggregate($first);
$aggregate->attach($second);
$aggregate->handleThrowable(new RuntimeException('Aggregate fixture'));

exampleCheck(['first', 'second'] === $calls, 'Aggregate ordering changed.');

$failure = new RuntimeException('Reporter failure');
$stopped = false;
$failFast = new ThrowableHandlerAggregate(
    new CallableThrowableHandler(
        /**
         * Fail the first reporter to demonstrate propagation.
         *
         * @param Throwable $error the synthetic aggregate input
         *
         * @throws RuntimeException the exact configured reporter failure
         */
        static function (Throwable $error) use ($failure): void {
            throw $failure;
        },
    ),
    new CallableThrowableHandler(
        /**
         * Record an unexpected delivery after the first reporter fails.
         *
         * @param Throwable $error the synthetic aggregate input
         */
        static function (Throwable $error) use (&$stopped): void {
            $stopped = true;
        },
    ),
);

try {
    $failFast->handleThrowable($failure);

    throw new LogicException('Reporter failure was swallowed.');
} catch (RuntimeException $error) {
    exampleCheck($error === $failure && !$stopped, 'Aggregate failure behavior changed.');
}

$phpErrors = new ThrowErrorExceptionPhpErrorHandler();
$level = error_reporting(E_ALL);

try {
    try {
        $phpErrors->handlePhpError(E_USER_WARNING, 'Converted warning', __FILE__, __LINE__);

        throw new LogicException('An enabled warning was not converted.');
    } catch (ErrorException $error) {
        exampleCheck(E_USER_WARNING === $error->getSeverity(), 'PHP severity was lost.');
    }

    error_reporting(0);
    $phpErrors->handlePhpError(E_USER_WARNING, 'Suppressed warning', __FILE__, __LINE__);
} finally {
    error_reporting($level);
}

$shutdownCalls = 0;
$shutdown = new CallableShutdownHandler(
    /** Demonstrate direct invocation of a custom shutdown adapter. */
    static function () use (&$shutdownCalls): void {
        ++$shutdownCalls;
    },
);
$shutdown->handleShutdown();

exampleCheck(1 === $shutdownCalls, 'Custom shutdown callback was not invoked.');

// No fatal error exists in this fresh process, so the default adapter is a no-op.
(new PhpLastErrorShutdownHandler($phpErrors))->handleShutdown();

echo 'callable, aggregate, conversion, suppression and shutdown adapters passed' . PHP_EOL;
