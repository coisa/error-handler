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
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Psr\Container\ContainerExceptionInterface;

use function FastForward\Container\container;

require $argv[1] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';

$reported = [];
$reporter = new CallableThrowableHandler(
    /**
     * Record the required application override's exact diagnostic input.
     *
     * @param Throwable $throwable the original synthetic event
     *
     * @return void
     */
    static function (Throwable $throwable) use (&$reported): void {
        $reported[] = $throwable;
    },
);

$application = new ArrayServiceProvider([
    // @return ThrowableHandlerInterface the application override, not the default empty aggregate
    ThrowableHandlerInterface::class => static fn(): ThrowableHandlerInterface => $reporter,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
$handler = $services->get(ErrorHandlerInterface::class);

if (!$handler instanceof ErrorHandler || $services->get(ErrorHandler::class) !== $handler
    || $services->get(ThrowableHandlerInterface::class) !== $reporter) {
    throw new RuntimeException('Merged application definitions must win without replacing service identities.');
}

$original = new RuntimeException('Synthetic required-override diagnostic.');
$handler->handleThrowable($original);
if ([$original] !== $reported) {
    throw new RuntimeException('The required override must receive the original event once.');
}

$failure = new LogicException('Synthetic required reporter construction failure.');
$attempts = 0;
$failingApplication = new ArrayServiceProvider([
    /*
     * Reject required reporter construction through the actual provider graph.
     *
     * @return ThrowableHandlerInterface this configured factory never returns
     *
     * @throws LogicException the original synthetic application failure
     */
    ThrowableHandlerInterface::class => static function () use ($failure, &$attempts): ThrowableHandlerInterface {
        ++$attempts;

        throw $failure;
    },
]);
$failingServices = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $failingApplication));
$caught = null;

try {
    $failingServices->get(ErrorHandlerInterface::class);
} catch (Throwable $throwable) {
    $caught = $throwable;
}

if ($attempts < 1 || null === $caught) {
    throw new RuntimeException('Failed required construction must be reached and must not silently yield a default handler.');
}

if ($caught !== $failure && !$caught instanceof ContainerExceptionInterface) {
    throw new RuntimeException('FastForward must expose the original or its PSR-11-normalized construction failure.', 0, $caught);
}

echo 'Production provider aliases and required overrides passed on PHP ' . PHP_VERSION . PHP_EOL;
