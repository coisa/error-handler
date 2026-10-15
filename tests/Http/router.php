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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$factory = new Psr17Factory();
$reporter = new CallableThrowableHandler(static function (): void {
    // A production application can log privately without rendering the exception.
});
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), new ThrowableResponseFactory(
    new ThrowableStreamFactory($factory, $reporter),
    $factory
));
$next = new class () implements RequestHandlerInterface {
    /**
     * Executes the synthetic downstream behavior for the requested test route.
     *
     * @param ServerRequestInterface $request The synthetic incoming request.
     *
     * @return ResponseInterface The fixture result.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/unavailable') {
            throw new RuntimeException('private upstream address', 503);
        }
        if ($request->getUri()->getPath() === '/type-error') {
            strlen([]);
        }
        throw new RuntimeException('private connection string');
    }
};
$response = $middleware->process($factory->createServerRequest('GET', $_SERVER['REQUEST_URI']), $next);
http_response_code($response->getStatusCode());
header('Content-Type: text/plain; charset=utf-8');
echo $response->getBody();
