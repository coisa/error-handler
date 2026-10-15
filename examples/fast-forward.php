<?php

declare(strict_types=1);

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

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$application = new class () implements ServiceProviderInterface {
    /**
     * Supplies application service definitions for the integration fixture.
     *
     * @return array<class-string, callable> Application service factories.
     */
    public function getFactories(): array
    {
        return [
            CallableThrowableHandler::class => static fn (): CallableThrowableHandler =>
                new CallableThrowableHandler(static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                }),
        ];
    }

    /**
     * Declares no application service extensions.
     *
     * @return array<string, callable> An empty extension map.
     */
    public function getExtensions(): array
    {
        return [];
    }
};

$services = container(ErrorHandlerServiceProvider::class, $application);

/**
 * @var ErrorHandlerInterface $errors
 */
$errors = $services->get(ErrorHandlerInterface::class);

$errors->register();

try {
    trigger_error('FastForward provider warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}
