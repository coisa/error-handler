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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Config\config;
use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

exampleCheck(
    PHP_VERSION_ID >= 80300 && function_exists('FastForward\Container\container'),
    'Install fast-forward/container:^1.9 in the application and use PHP 8.3 or newer.',
);
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'debug'], true), 'Choose the public or debug example mode.');

// This CLI choice stands in for trusted application configuration, never request headers.
$settings = config(['error_handler' => ['json' => ['debug' => 'debug' === $mode]]]);
$messages = new Psr17Factory();
$original = new RuntimeException('Private synthetic provider failure', 503);
$reports = [];
$reporter = new CallableThrowableHandler(
    /**
     * Record one original event without writing diagnostics into a public response.
     *
     * @param Throwable $event the original downstream failure
     */
    static function (Throwable $event) use (&$reports): void {
        $reports[] = $event; // Replace this recording sink with the application's private transport.
    },
);

$application = new ArrayServiceProvider([
    ResponseFactoryInterface::class => new ServiceFactory($messages),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($reporter),
]);

// Separate providers are first-wins: the JSON selection must precede the base capture alias.
if ('debug' === $mode) {
    $services = container(
        new JsonErrorHandlerServiceProvider($settings->get('error_handler.json.debug', false)),
        ErrorHandlerServiceProvider::class,
        $application,
    );
} else {
    $services = container(JsonErrorHandlerServiceProvider::class, ErrorHandlerServiceProvider::class, $application);
}

// Cache the factory, then invoke it for a distinct handler and wrapper for each HTTP scope.
$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);

$middleware = $middlewareFactory($services);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic downstream failure */
    private Throwable $original;

    /**
     * Retain original identity for the private reporter.
     *
     * @param Throwable $original the event that the middleware will catch
     */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * Fail a synthetic route without emission or registration of its own callbacks.
     *
     * @param ServerRequestInterface $request the synthetic HTTP request
     *
     * @return ResponseInterface no response is returned by this failing route
     *
     * @throws Throwable the original downstream failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$response = $middleware->process($messages->createServerRequest('GET', '/provider-json'), $next);

exampleCheck([$original] === $reports, 'The profile must report the exact original once.');
exampleCheck(503 === $response->getStatusCode(), 'Expected the native HTTP error code.');
exampleCheck('application/json' === $response->getHeaderLine('Content-Type'), 'Expected JSON.');

if ('debug' === $mode) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    exampleCheck(
        $original->getMessage() === $payload['exception']['message'],
        'Trusted debug configuration did not reach the renderer.',
    );
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
} else {
    exampleCheck('{"error":"Request failed"}' === (string) $response->getBody(), 'Public JSON leaked diagnostics.');
}

// Applications emit the response; this CLI fixture prints only its verification summary.
echo 'Interop JSON ' . $mode . ': configured profile, fresh scope and one private report passed' . PHP_EOL;
