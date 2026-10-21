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
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\CallableFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;

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

$mode = $argv[2] ?? 'default';

if (!in_array($mode, ['public', 'default', 'configured'], true)) {
    throw new InvalidArgumentException('Choose public, default or configured after the consumer autoloader.');
}

// Trusted debug permits HTML; the current request must still accept it.
$reporter = new RecordingThrowableHandler();
$http = new Psr17Factory();
$page = null;
$factories = [
    ResponseFactoryInterface::class => new ServiceFactory($http),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($reporter),
];

if ('configured' === $mode) {
    if (!class_exists(PrettyPageHandler::class)) {
        throw new RuntimeException('The configured development page needs filp/whoops:^2.18.7.');
    }

    $settings = config(['development' => ['page_title' => 'Synthetic configured diagnostics']]);

    // Prepare configuration before get(): an aggregate does not disable autowire fallback.
    $page = new PrettyPageHandler();
    $page->setPageTitle($settings->get('development.page_title'));
    $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
    $page->hideSuperglobalKey('_COOKIE', 'session');
    $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

    $factories[PrettyPageHandler::class] = new ServiceFactory($page);
}

if ('public' !== $mode) {
    // Whoops is an explicit application choice; package presence never selects it.
    $factories[ThrowableResponseFactoryInterface::class] = new CallableFactory(
        /**
         * Retain request negotiation while selecting a diagnostic HTML integration.
         *
         * @param ContainerInterface $services the composed application services
         *
         * @return NegotiatingThrowableResponseFactory the explicit application response policy
         */
        static function (ContainerInterface $services): NegotiatingThrowableResponseFactory {
            /** @var ThrowableResponseFactoryInterface $fallback the generic error response */
            $fallback = $services->get(ThrowableResponseFactory::class);

            return new NegotiatingThrowableResponseFactory($fallback, [
                'text/plain' => static fn() => $fallback,
                'application/json' => static fn() => $services->get(JsonThrowableResponseFactory::class),
                'application/problem+json' => static fn() => $services->get(JsonThrowableResponseFactory::class),
                'text/html' => static fn() => $services->get(WhoopsThrowableResponseFactory::class),
            ]);
        },
    );
}

$application = new ArrayServiceProvider($factories);

$services = container($application, new ErrorHandlerServiceProvider(debug: 'public' !== $mode));

if (!$services->get(ThrowableResponseFactoryInterface::class) instanceof NegotiatingThrowableResponseFactory) {
    throw new RuntimeException('Expected the request-aware response factory.');
}

/** @var ErrorHandlerMiddlewareFactory $middlewareFactory the reusable fresh-scope service */
$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);

$firstScope = $middlewareFactory($services);
$secondScope = $middlewareFactory($services);

if ($firstScope === $secondScope) {
    throw new RuntimeException('Expected distinct middleware scopes.');
}

$failures = [
    new RuntimeException(base64_encode('First private provider diagnostic'), 503),
    new RuntimeException(base64_encode('Second private provider diagnostic'), 404),
];

$first = $firstScope->process(
    $http->createServerRequest('GET', 'http://example.test/first')->withHeader('Accept', 'text/html'),
    new ThrowingRequestHandler($failures[0]),
);

$second = $secondScope->process(
    $http->createServerRequest('GET', 'http://example.test/second')->withHeader('Accept', 'text/html'),
    new ThrowingRequestHandler($failures[1]),
);

if ($failures !== $reporter->getThrowables() || 503 !== $first->getStatusCode() || 404 !== $second->getStatusCode()) {
    throw new RuntimeException('Expected each original once with its HTTP status.');
}

if (
    'text/html; charset=UTF-8' !== $second->getHeaderLine('Content-Type')
    || ('public' !== $mode && 'no-store' !== $second->getHeaderLine('Cache-Control'))
) {
    throw new RuntimeException('Expected HTML and a no-store policy for diagnostics.');
}

if (
    str_contains((string) $second->getBody(), $failures[0]->getMessage())
    || ('public' !== $mode && !str_contains((string) $second->getBody(), $failures[1]->getMessage()))
    || ('public' === $mode && str_contains((string) $second->getBody(), $failures[1]->getMessage()))
) {
    throw new RuntimeException('Sequential rendering must replace the previous diagnostic.');
}

if (
    null !== $page && ($page !== $services->get(PrettyPageHandler::class)
    || !str_contains((string) $second->getBody(), '<title>Synthetic configured diagnostics</title>'))
) {
    throw new RuntimeException('Expected the shared application page and its retained configuration.');
}

echo 'public' === $mode
    ? 'Interop HTML public: Symfony renderer and original reports passed' . PHP_EOL
    : 'Interop Whoops ' . $mode . ': single provider, shared page and original reports passed' . PHP_EOL;
