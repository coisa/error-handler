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

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$application = new class implements ServiceProviderInterface {
    /**
     * Supplies application service definitions for the integration fixture.
     *
     * @return array<class-string, callable> application service factories
     */
    public function getFactories(): array
    {
        return [
            CallableThrowableHandler::class => static fn(): CallableThrowableHandler => new CallableThrowableHandler(
                /**
                 * Prints only this CLI recipe's synthetic diagnostic.
                 *
                 * @param Throwable $error the converted warning supplied for reporting
                 */
                static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                },
            ),
        ];
    }

    /**
     * Declares no application service extensions.
     *
     * @return array<string, callable> an empty extension map
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
