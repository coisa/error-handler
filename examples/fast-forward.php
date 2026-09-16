<?php

declare(strict_types=1);

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use Interop\Container\ServiceProviderInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$application = new class () implements ServiceProviderInterface {
    public function getFactories(): array
    {
        return [
            ThrowableHandlerInterface::class => static fn (): CallableThrowableHandler =>
                new CallableThrowableHandler(static function (Throwable $error): void {
                    printf("Reported: %s\n", $error->getMessage());
                }),
        ];
    }

    public function getExtensions(): array
    {
        return [];
    }
};

$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
/** @var ErrorHandlerInterface $errors */
$errors = $services->get(ErrorHandlerInterface::class);
$errors->register();
try {
    trigger_error('FastForward provider warning', E_USER_WARNING);
} finally {
    $errors->unregister();
}
