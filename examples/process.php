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
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'warning';
$reporter = new CallableThrowableHandler(
    /**
     * Record only synthetic process-fixture messages.
     *
     * @param Throwable $error the warning, uncaught failure or native fatal fixture
     */
    static function (Throwable $error): void {
        echo 'reported: ' . $error->getMessage() . PHP_EOL;
    },
);
$shutdown = 'shutdown' === $mode
    ? new CallableShutdownHandler(
        /** Demonstrate an application-owned shutdown callback. */
        static function (): void {
            echo 'shutdown callback' . PHP_EOL;
        },
    )
    : null;
$errors = new ErrorHandler($reporter, null, $shutdown);

if ('scope' === $mode) {
    /** Represent an application's already installed PHP error callback. */
    $previousError = static fn(): bool => true;

    /**
     * Represent an application's already installed exception callback.
     *
     * @param Throwable $error an unexpected failure escaping the fixture
     *
     * @throws LogicException if the surrounding callback is unexpectedly invoked
     */
    $previousException = static function (Throwable $error): void {
        throw new LogicException('The surrounding exception callback must remain unused.', 0, $error);
    };

    set_error_handler($previousError);
    set_exception_handler($previousException);

    $errors->register();

    try {
        trigger_error('Scoped warning', E_USER_WARNING);
    } finally {
        $errors->unregister();
    }

    $actualError = set_error_handler($previousError);
    restore_error_handler();
    $actualException = set_exception_handler($previousException);
    restore_exception_handler();

    restore_error_handler();
    restore_exception_handler();

    exampleCheck(
        $actualError === $previousError && $actualException === $previousException,
        'Callbacks were not restored.',
    );

    echo 'callbacks restored' . PHP_EOL;

    return;
}

$errors->register();

if ('uncaught' === $mode) {
    throw new RuntimeException('Uncaught fixture');
}

if ('fatal' === $mode) {
    exampleCheck(
        '1' === getenv('ERROR_HANDLER_BOUNDED_FIXTURE'),
        'Run the fatal fixture only through examples/verify.php.',
    );

    eval('function exampleFatalDuplicate() {} function exampleFatalDuplicate() {}');
}

if ('warning' === $mode) {
    trigger_error('Process warning', E_USER_WARNING);

    echo 'continued' . PHP_EOL;

    return;
}

exampleCheck('shutdown' === $mode, 'Unknown process example mode.');

echo 'leaving main' . PHP_EOL;
