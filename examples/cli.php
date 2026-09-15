<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;

require dirname(__DIR__) . '/vendor/autoload.php';

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . PHP_EOL);
});
$handler = new ErrorHandler($reporter);
$handler->register();

trigger_error('Example warning; execution continues.', E_USER_WARNING);
throw new RuntimeException('Example uncaught exception; exit status is 1.');
