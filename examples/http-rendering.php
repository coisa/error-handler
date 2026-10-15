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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/vendor/autoload.php';

$privateDiagnostics = [];
$reporter = new CallableThrowableHandler(
    static function (Throwable $error) use (&$privateDiagnostics): void {
        $privateDiagnostics[] = $error; // Replace with a private logger.
    }
);
$publicRenderer = new CallableThrowableHandler(static function (): void {
    echo json_encode(['error' => 'Service unavailable'], JSON_THROW_ON_ERROR);
});
$psr17 = new Psr17Factory();
$streams = new ThrowableStreamFactory($psr17, new ThrowableHandlerAggregate($reporter, $publicRenderer));
$responses = new ThrowableResponseFactory($streams, $psr17);
$response = $responses->createResponseFromThrowable(new RuntimeException('Private credentials', 503))
    ->withHeader('Content-Type', 'application/json');

printf("HTTP %d | %s\n", $response->getStatusCode(), $response->getHeaderLine('Content-Type'));
echo (string) $response->getBody() . PHP_EOL;
printf("Private reports: %d\n", count($privateDiagnostics));
