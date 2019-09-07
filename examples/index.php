<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);
require __DIR__ . '/../vendor/autoload.php';

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

$firstHandler = new CallableThrowableHandler(function (Throwable $throwable): void {
    echo 'First Handler' . "\n";
    echo $throwable;
});

$secondHandler = new CallableThrowableHandler(function (Throwable $throwable): void {
    echo 'Second Handler' . "\n";

    // Raise exception to previous error-handler!?
    throw $throwable;
});

$firstErrorHandler  = new ErrorHandler($firstHandler);
$secondErrorHandler = new ErrorHandler($secondHandler);

$firstErrorHandler->register();
$secondErrorHandler->register();

throw new InvalidArgumentException('Exception', 321);
