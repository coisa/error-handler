<?php
require __DIR__ . '/../vendor/autoload.php';

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\VarExportHandler;

$message = "
    <blockquote>For obvious reasons ErrorHandler will only ignore buffer outputs after him.</blockquote>
    <blockquote>First thing you need to set in your application is how you will deal with errors!</blockquote>
";
echo $message;

$handler = new VarExportHandler();
$errorHandler = new ErrorHandler($handler);

echo "<blockquote>Every content of current buffer will be ignored</blockquote>";

$previous = new RuntimeException('Testing error handler level 1', 123);

throw new InvalidArgumentException('Testing error handler level 2', 321, $previous);