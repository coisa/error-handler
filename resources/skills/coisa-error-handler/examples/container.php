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

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Laminas\ServiceManager\ServiceManager;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

$reporter = new RecordingThrowableHandler();

$application = new ServiceManager([
    'services' => [ThrowableHandlerInterface::class => $reporter],
]);

$services = new ErrorHandlerContainer($application);
$errors = $services->get(ErrorHandler::class);
$fresh = (new ErrorHandlerFactory())($services);
$failure = new RuntimeException('Synthetic container failure');

$errors->handleThrowable($failure);

if ($errors !== $services->get(ErrorHandler::class) || $fresh === $errors) {
    throw new RuntimeException('Expected a cached service and a distinct explicitly constructed scope.');
}

if ([$failure] !== $reporter->getThrowables()) {
    throw new RuntimeException('Expected the application reporter to receive the original once.');
}

echo 'legacy container: application reporter, caching and fresh scope passed' . PHP_EOL;
