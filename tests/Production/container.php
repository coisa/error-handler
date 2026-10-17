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

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Exception\ContainerException;
use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use Interop\Container\ServiceProviderInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

require $argv[1] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';

$interopProviders = [
    new ErrorHandlerServiceProvider(),
    new JsonErrorHandlerServiceProvider(),
    new WhoopsErrorHandlerServiceProvider(),
];

foreach ($interopProviders as $interopProvider) {
    if (!$interopProvider instanceof ServiceProviderInterface) {
        throw new RuntimeException('Optional Interop providers must load without FastForward or development packages.');
    }
}

$container = new ErrorHandlerContainer();
$provider = new ConfigProvider();
$configuration = $provider();

if (!$container instanceof ContainerInterface || $configuration['dependencies'] !== $provider->getDependencies()) {
    throw new RuntimeException('The public container and ConfigProvider must load without development dependencies.');
}

$aggregate = $container->get(ThrowableHandlerInterface::class);
$handler = $container->get(ErrorHandler::class);

if (!$aggregate instanceof ThrowableHandlerAggregate || !$handler instanceof ErrorHandler) {
    throw new RuntimeException('The configured aliases and factories must resolve the core services.');
}

if (!$container->has(ErrorHandler::class) || $container->get(ErrorHandler::class) !== $handler) {
    throw new RuntimeException('The container must retain its cached handler.');
}

$reported = [];
$reporter = new CallableThrowableHandler(
    /** Record only the synthetic throwable passed through the public composition APIs. */
    static function (Throwable $throwable) use (&$reported): void {
        $reported[] = $throwable;
    },
);
$failure = new RuntimeException('Synthetic production-install regression.');
$core = new ErrorHandler($reporter);
$core->handleThrowable($failure);
$aggregate->attach($reporter);
$handler->handleThrowable($failure);
(new ErrorHandlerFactory())($container)->handleThrowable($failure);

if ($reported !== [$failure, $failure, $failure]) {
    throw new RuntimeException('Core construction and container factories must preserve throwable reporting.');
}

if ($container->has('missing-production-fixture-service')) {
    throw new RuntimeException('The container must reject an unknown service.');
}

try {
    $container->get('missing-production-fixture-service');

    throw new RuntimeException('An unknown service must throw a PSR-11 exception.');
} catch (NotFoundExceptionInterface $exception) {
    if (!$exception instanceof ContainerExceptionInterface) {
        throw new RuntimeException('Missing entries must also satisfy the PSR-11 container exception contract.');
    }
}

if (!ContainerException::createFromThrowable($failure) instanceof ContainerExceptionInterface) {
    throw new RuntimeException('Construction failures must retain their PSR-11 exception contract.');
}

echo 'Production container passed on PHP ' . PHP_VERSION . PHP_EOL;
