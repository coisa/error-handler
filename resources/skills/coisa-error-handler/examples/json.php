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
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

require_once __DIR__ . '/ThrowingRequestHandler.php';

foreach ([JsonThrowableResponseFactory::class, ReportingThrowableResponseFactory::class] as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('This route needs coisa/error-handler:^2.5; missing ' . $class);
    }
}

$mode = $argv[2] ?? 'public';

if (!in_array($mode, ['public', 'debug'], true)) {
    throw new InvalidArgumentException('Choose public or debug after the consumer autoloader.');
}

// This explicit CLI choice represents trusted application configuration, never request input.
$configuration = ['debug' => 'debug' === $mode];
$reporter = new RecordingThrowableHandler();
$http = new Psr17Factory();

/** @return RuntimeException a synthetic failure with a native call frame */
$createFailure = static fn(): RuntimeException => new RuntimeException('Private JSON diagnostic', 503);

$failure = $createFailure();

$json = new JsonThrowableResponseFactory($http, $http, $configuration['debug']);

$rendering = new ReportingThrowableResponseFactory($reporter, $json);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $rendering);

$entryLevel = ob_get_level();
$response = $middleware->process(
    $http->createServerRequest('GET', 'http://example.test/json'),
    new ThrowingRequestHandler($failure),
);

if ([$failure] !== $reporter->getThrowables() || $entryLevel !== ob_get_level()) {
    throw new RuntimeException('Expected one private report without changing the output-buffer stack.');
}

if (503 !== $response->getStatusCode() || 'application/json' !== $response->getHeaderLine('Content-Type')) {
    throw new RuntimeException('Expected the original HTTP status and JSON content type.');
}

if ($failure->getMessage() === $response->getReasonPhrase()) {
    throw new RuntimeException('The diagnostic must not become the response reason.');
}

if ($configuration['debug']) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    if ($failure->getMessage() !== $payload['exception']['message'] || [] === $payload['exception']['trace']) {
        throw new RuntimeException('Expected explicitly configured development metadata.');
    }

    foreach ($payload['exception']['trace'] as $frame) {
        if (array_key_exists('args', $frame) || array_key_exists('object', $frame)) {
            throw new RuntimeException('Trace arguments and objects must remain private.');
        }
    }

    if ('no-store' !== $response->getHeaderLine('Cache-Control')) {
        throw new RuntimeException('Development responses must disable caching.');
    }
} elseif ('{"error":"Request failed"}' !== (string) $response->getBody()) {
    throw new RuntimeException('Expected generic JSON without the private diagnostic.');
}

echo 'JSON ' . $mode . ': original reported once and explicit response passed' . PHP_EOL;
