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
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Server\MiddlewareInterface;

use function FastForward\Container\container;

require dirname(__DIR__) . '/vendor/autoload.php';

$reported = 0;
$psr17 = new Psr17Factory();
$reporter = new CallableThrowableHandler(static function () use (&$reported): void {
    ++$reported;
});
$application = new ArrayServiceProvider([
    ThrowableHandlerInterface::class => static fn (): ThrowableHandlerInterface => $reporter,
    ResponseFactoryInterface::class => static fn (): ResponseFactoryInterface => $psr17,
    StreamFactoryInterface::class => static fn (): StreamFactoryInterface => $psr17,
]);
$services = container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
$makeHandler = $services->get(ErrorHandlerFactory::class);
$first = $makeHandler($services);
$second = $makeHandler($services);
$makeMiddleware = $services->get(ErrorHandlerMiddlewareFactory::class);
$outer = $makeMiddleware($services);
$inner = $makeMiddleware($services);
$downstream = new class () implements RequestHandlerInterface {
    /**
     * Throws a synthetic downstream failure for the inner scope.
     *
     * @param ServerRequestInterface $request The synthetic request.
     * @return ResponseInterface The response produced by the inner middleware.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Nested failure');
    }
};
$next = new class ($inner, $downstream) implements RequestHandlerInterface {
    /**
     * Stores the independently constructed inner middleware and application handler.
     *
     * @param MiddlewareInterface $inner The inner error middleware.
     * @param RequestHandlerInterface $downstream The application request handler.
     */
    public function __construct(private MiddlewareInterface $inner, private RequestHandlerInterface $downstream)
    {
    }

    /**
     * Enters the inner scope while the outer scope remains registered.
     *
     * @param ServerRequestInterface $request The incoming request.
     * @return ResponseInterface The inner response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->inner->process($request, $this->downstream);
    }
};
$process = $services->get(ErrorHandlerInterface::class);
$process->register();
try {
    $response = $outer->process($psr17->createServerRequest('GET', 'https://example.test'), $next);
    trigger_error('Process handler restored', E_USER_WARNING);
} finally {
    $process->unregister();
}
printf(
    "Fresh handlers: %s | Fresh middleware: %s\n",
    $first !== $second ? 'yes' : 'no',
    $outer !== $inner ? 'yes' : 'no'
);
printf("HTTP %d | Reports: %d\n", $response->getStatusCode(), $reported);
