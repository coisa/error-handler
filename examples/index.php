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

\error_reporting(E_ALL);
\ini_set('display_errors', 1);

$handler = new CallableThrowableHandler(function (Throwable $throwable): void {
    echo 'Throwable error!' . "\n";
    \var_dump($throwable);
});

$errorHandler = new ErrorHandler($handler);
$errorHandler->register();

$previous = new RuntimeException('Testing error handler level 1', 123);

throw new InvalidArgumentException('Testing error handler level 2', 321, $previous);
