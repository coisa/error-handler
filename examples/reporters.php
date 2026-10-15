<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link https://github.com/coisa/error-handler
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license https://opensource.org/licenses/MIT MIT License
 */

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;

require dirname(__DIR__) . '/vendor/autoload.php';

$deliveries = [];
$reporters = new ThrowableHandlerAggregate(
    new CallableThrowableHandler(static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'first: ' . $error->getMessage();
    })
);
$reporters->attach(new CallableThrowableHandler(
    static function (Throwable $error) use (&$deliveries): void {
        $deliveries[] = 'second: ' . $error->getMessage();
    }
));

$errors = new ErrorHandler($reporters);
// Direct delivery needs no registration and does not terminate the process.
$errors->handleThrowable(new RuntimeException('Synthetic failure'));
echo implode(PHP_EOL, $deliveries) . PHP_EOL;
