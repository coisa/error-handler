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
use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use Laminas\ServiceManager\ServiceManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

require __DIR__ . '/bootstrap.php';

$reports = 0;
$reporter = new CallableThrowableHandler(
    /**
     * Count synthetic diagnostics without registering native callbacks.
     *
     * @param Throwable $error the synthetic composition failure
     */
    static function (Throwable $error) use (&$reports): void {
        ++$reports;
    },
);

// Applications own their parent container and its optional dependencies.
$application = new ServiceManager();

$provider = new ConfigProvider();
$configuration = $provider();

$native = new ServiceManager($provider->getDependencies());

$messages = new Psr17Factory();

$dispatcher = new EventDispatcher(new ListenerProviderAggregate());

foreach ([$application, $native] as $services) {
    $services->setService(CallableThrowableHandler::class, $reporter);
    $services->setService(EventDispatcherInterface::class, $dispatcher);
    $services->setService(ResponseFactoryInterface::class, $messages);
    $services->setService(StreamFactoryInterface::class, $messages);
}

$legacy = new ErrorHandlerContainer($application);

$process = $legacy->get(ErrorHandler::class);

exampleCheck($process instanceof ErrorHandler, 'The container returned the wrong core service.');

$process->handleThrowable(new RuntimeException('Legacy container fixture'));

exampleCheck($legacy->get(ErrorHandler::class) === $process, 'The legacy container must cache its handler.');

$fresh = (new ErrorHandlerFactory())($legacy);

exampleCheck($fresh !== $process, 'Direct factory invocation must construct a new handler.');

$fresh->handleThrowable(new RuntimeException('Fresh factory fixture'));

// A ConfigProvider-aware application can consume the same maps directly.
exampleCheck($configuration['dependencies'] === $provider->getDependencies(), 'ConfigProvider maps changed.');

$native->get(ErrorHandler::class)->handleThrowable(new RuntimeException('ConfigProvider fixture'));

foreach (array_keys($provider->getFactories()) as $service) {
    exampleCheck($legacy->get($service) instanceof $service, 'A legacy provider factory did not resolve its service.');
    exampleCheck($native->get($service) instanceof $service, 'A native provider factory did not resolve its service.');
}

exampleCheck(3 === $reports, 'One of the composition paths lost its reporter.');

echo 'legacy container, cached services, fresh factory and ConfigProvider passed' . PHP_EOL;
