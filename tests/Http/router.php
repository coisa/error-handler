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
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../Fire/bootstrap.php';

require getenv('ERROR_HANDLER_TEST_AUTOLOAD') ?: dirname(__DIR__, 2) . '/vendor/autoload.php';

$factory = new Psr17Factory();
$reports = 0;
$reporter = new CallableThrowableHandler(static function (Throwable $throwable) use (&$reports): void {
    ++$reports;
    echo 'legacy captured: ' . $throwable->getMessage();
});
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), new ThrowableResponseFactory(
    new ThrowableStreamFactory($factory, $reporter),
    $factory,
));
$next = new class implements RequestHandlerInterface {
    /**
     * Executes one synthetic downstream failure without contacting an application.
     *
     * @param ServerRequestInterface $request the loopback fixture request
     *
     * @return ResponseInterface the response contract, never reached by these routes
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        switch ($request->getUri()->getPath()) {
            case '/unavailable':
                throw new RuntimeException('synthetic upstream address', 503);

            case '/client-error':
                throw new RuntimeException('synthetic client failure', 404);

            case '/type-error':
                throw new TypeError('synthetic type mismatch');

            default:
                throw new RuntimeException('synthetic downstream failure');
        }
    }
};

$previousError = static fn(): bool => true;
$previousException = static function (Throwable $throwable): void {
    throw new LogicException('The previous exception callback was unexpectedly invoked.', 0, $throwable);
};
set_error_handler($previousError);
set_exception_handler($previousException);
$response = $middleware->process($factory->createServerRequest('GET', $_SERVER['REQUEST_URI']), $next);
$restoredError = set_error_handler($previousError);
restore_error_handler();
$restoredException = set_exception_handler($previousException);
restore_exception_handler();
restore_error_handler();
restore_exception_handler();

http_response_code($response->getStatusCode());
header('Content-Type: text/plain; charset=utf-8');
header('X-Fixture-Reports: ' . $reports);
$restored = $restoredError === $previousError && $restoredException === $previousException;
header('X-Fixture-Callbacks-Restored: ' . ($restored ? 'yes' : 'no'));
echo $response->getBody();
