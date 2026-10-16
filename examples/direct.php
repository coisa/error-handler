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
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require __DIR__ . '/bootstrap.php';

$errors = new ErrorHandler(new CallableThrowableHandler(
    /**
     * Print only the synthetic message used by this executable example.
     *
     * @param Throwable $error the synthetic failure to report
     */
    static function (Throwable $error): void {
        echo 'reported: ' . $error->getMessage() . PHP_EOL;
    },
));

$errors->handleThrowable(new RuntimeException('Example failure'));

echo 'continued' . PHP_EOL;
