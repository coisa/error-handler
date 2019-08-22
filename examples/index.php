<?php
require __DIR__ . '/../vendor/autoload.php';

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\VarExportHandler;

error_reporting(E_ALL);
ini_set('display_errors', 1);

$handler = new CallableThrowableHandler(function (\Throwable $throwable) {
    echo 'Throwable error!' . "\n";
    var_dump($throwable);
});

$errorHandler = new ErrorHandler($handler);
$errorHandler->register();

$previous = new RuntimeException('Testing error handler level 1', 123);

throw new InvalidArgumentException('Testing error handler level 2', 321, $previous);
