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
use CoiSA\ErrorHandler\Example\PublicThrowableResponseFactory;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

require_once __DIR__ . '/PublicThrowableResponseFactory.php';

require_once __DIR__ . '/ThrowingRequestHandler.php';

$reporter = new RecordingThrowableHandler();
$http = new Psr17Factory();
$failure = new RuntimeException('Private diagnostic detail', 404);

$middleware = new ErrorHandlerMiddleware(
    new ErrorHandler($reporter),
    new PublicThrowableResponseFactory($http, $http),
);

$response = $middleware->process(
    $http->createServerRequest('GET', 'http://example.test/'),
    new ThrowingRequestHandler($failure),
);

if (500 !== $response->getStatusCode() || '{"error":"Request failed"}' !== (string) $response->getBody()) {
    throw new RuntimeException('Expected the fixed application response without diagnostic detail.');
}

if ([$failure] !== $reporter->getThrowables()) {
    throw new RuntimeException('Expected one private delivery of the original request failure.');
}

echo 'HTTP: private report once and fixed public response passed' . PHP_EOL;
