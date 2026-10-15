<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link https://github.com/coisa/error-handler
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license https://opensource.org/licenses/MIT MIT License
 */

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = [];

$psr17 = new Psr17Factory();

$reporter = new CallableThrowableHandler(static function (Throwable $error) use (&$reported): void {
    $reported[] = $error; // Replace with a private logger; do not echo diagnostics.
});

$application = new ArrayServiceProvider([
    CallableThrowableHandler::class => static fn (): CallableThrowableHandler => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);

$services = container(ErrorHandlerServiceProvider::class, $application);

$process = $services->get(ErrorHandlerInterface::class);
$middleware = $services->get(ErrorHandlerMiddleware::class);

$downstream = new class () implements RequestHandlerInterface {
    /**
     * Simulates an application failure with an HTTP error status.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return ResponseInterface A response supplied by the error middleware.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Private database diagnostic', 503);
    }
};

$process->register();

try {
    $response = $middleware->process($psr17->createServerRequest('GET', 'https://example.test'), $downstream);

    // The process handler is active again after the middleware scope ends.
    trigger_error('Synthetic post-request warning', E_USER_WARNING);
} finally {
    $process->unregister();
}

printf("HTTP %d | %s\n", $response->getStatusCode(), (string) $response->getBody());
printf("Reported: %d\n", count($reported));

// Your HTTP framework/emitter sends the response; this example only inspects it.
