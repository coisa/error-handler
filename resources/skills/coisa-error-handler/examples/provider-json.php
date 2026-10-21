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

use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function FastForward\Config\config;
use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

require_once __DIR__ . '/ThrowingRequestHandler.php';

if (PHP_VERSION_ID < 80300 || !function_exists('FastForward\Container\container')) {
    throw new RuntimeException('This source requires PHP 8.3+ and its FastForward runtime dependency.');
}

foreach ([ErrorHandlerServiceProvider::class] as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('The selected library installation lacks ' . $class);
    }
}

$mode = $argv[2] ?? 'public';

if (!in_array($mode, ['public', 'debug'], true)) {
    throw new InvalidArgumentException('Choose public or debug after the consumer autoloader.');
}

// Resolve a real boolean from trusted configuration before constructing the provider.
$settings = config(['error_handler' => ['debug' => 'debug' === $mode]]);
$reporter = new RecordingThrowableHandler();
$http = new Psr17Factory();
$failure = new RuntimeException('Private provider diagnostic', 503);

$application = new ArrayServiceProvider([
    ResponseFactoryInterface::class => new ServiceFactory($http),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($reporter),
]);

$services = container(
    $application,
    new ErrorHandlerServiceProvider(debug: $settings->get('error_handler.debug', false)),
);

if (!$services->get(ThrowableResponseFactoryInterface::class) instanceof NegotiatingThrowableResponseFactory) {
    throw new RuntimeException('Expected the request-aware default response factory.');
}

/** @var ErrorHandlerMiddlewareFactory $middlewareFactory the reusable fresh-scope service */
$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);

$middleware = $middlewareFactory($services);

if ($middleware === $middlewareFactory($services)) {
    throw new RuntimeException('Explicit factory invocation must construct distinct middleware scopes.');
}

$response = $middleware->process(
    $http->createServerRequest('GET', 'http://example.test/provider-json')->withHeader('Accept', 'application/json'),
    new ThrowingRequestHandler($failure),
);

if ([$failure] !== $reporter->getThrowables() || 503 !== $response->getStatusCode()) {
    throw new RuntimeException('Expected one original private report and its HTTP status.');
}

if ('application/json' !== $response->getHeaderLine('Content-Type')) {
    throw new RuntimeException('Expected JSON selected by the current request.');
}

$publicBody = '{"type":"about:blank","title":"Service Unavailable","status":503,"detail":"Request failed"}';

if ('debug' === $mode) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

    if ($failure->getMessage() !== $payload['detail']) {
        throw new RuntimeException('Trusted debug configuration must reach the JSON renderer.');
    }

    if ('no-store' !== $response->getHeaderLine('Cache-Control')) {
        throw new RuntimeException('Development responses must disable caching.');
    }
} elseif ($publicBody !== (string) $response->getBody()) {
    throw new RuntimeException('Expected generic JSON without the private diagnostic.');
}

echo 'Interop JSON ' . $mode . ': single provider and fresh scope passed' . PHP_EOL;
