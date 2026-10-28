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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Exception\ErrorException;

require __DIR__ . '/bootstrap.php';

$calls = [];
$reports = [];
$shutdowns = 0;
$errors = new ErrorHandler(
    /**
     * Record a converted synthetic failure without output or a real transport.
     *
     * @param Throwable $error the callback's converted error
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error;
    },
    /**
     * Own conversion and suppression explicitly; normalization adds neither policy.
     *
     * @param int    $code     the synthetic PHP severity
     * @param string $message  the synthetic diagnostic message
     * @param string $filename the supplied source filename
     * @param int    $line     the supplied source line
     *
     * @throws ErrorException when this callback selects an enabled error
     */
    static function (int $code, string $message, string $filename, int $line) use (&$calls): void {
        $calls[] = [$code, $message, $filename, $line];
        if (error_reporting() & $code) {
            throw ErrorException::fromPhpError($message, $code, $code, $filename, $line);
        }
    },
    /** Count explicit active shutdown notifications without recovering or terminating a process. */
    static function () use (&$shutdowns): void {
        ++$shutdowns;
    },
);

exampleCheck([] === $calls && [] === $reports && 0 === $shutdowns, 'Construction invoked a callback.');
$previous = error_reporting(E_ALL);

try {
    $errors->handlePhpError(E_USER_WARNING, 'Synthetic enabled input', __FILE__, 42);
    error_reporting(0);
    $errors->handlePhpError(E_USER_WARNING, 'Synthetic disabled input', __FILE__, 43);
} finally {
    error_reporting($previous);
}

exampleCheck([
    [E_USER_WARNING, 'Synthetic enabled input', __FILE__, 42],
    [E_USER_WARNING, 'Synthetic disabled input', __FILE__, 43],
] === $calls, 'The callback did not receive the four original arguments.');
exampleCheck(
    1 === count($reports) && $reports[0] instanceof ErrorException,
    'Explicit mask/conversion policy changed.',
);
exampleCheck('Synthetic enabled input' === $reports[0]->getMessage(), 'Converted diagnostic metadata was lost.');

$errors->handleShutdown();
exampleCheck(0 === $shutdowns, 'An inactive handler invoked the shutdown policy.');
$errors->register();

try {
    $errors->handleShutdown();
} finally {
    $errors->unregister();
}

exampleCheck(1 === $shutdowns, 'The active shutdown callback was not invoked once.');

echo 'callable PHP errors: four arguments, explicit mask policy and conversion passed' . PHP_EOL;
