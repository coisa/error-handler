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
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

/** @var array{reporter: RecordingThrowableHandler} $configuration */
$configuration = require __DIR__ . '/config/error-handler.php';
$reporter = $configuration['reporter'];

$application = new ArrayServiceProvider([
    ThrowableHandlerInterface::class => new ServiceFactory($reporter),
]);

$services = container(new AggregateServiceProvider(
    new ErrorHandlerServiceProvider(),
    $application,
));

$errors = $services->get(ErrorHandler::class);
$factory = $services->get(ErrorHandlerFactory::class);
$fresh = $factory($services);
$failure = new RuntimeException('Synthetic container failure');

$errors->handleThrowable($failure);

if ($errors !== $services->get(ErrorHandler::class) || $fresh === $errors) {
    throw new RuntimeException('Expected a cached service and a distinct explicitly constructed scope.');
}

if ([$failure] !== $reporter->getThrowables()) {
    throw new RuntimeException('Expected the application reporter to receive the original once.');
}

echo 'Interop provider: application reporter, caching and fresh scope passed' . PHP_EOL;
