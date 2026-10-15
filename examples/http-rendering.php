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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

require dirname(__DIR__) . '/vendor/autoload.php';

$privateDiagnostics = [];
$reporter = new CallableThrowableHandler(
    static function (Throwable $error) use (&$privateDiagnostics): void {
        $privateDiagnostics[] = $error; // Replace with a private logger; emit no response body.
    }
);

$psr17 = new Psr17Factory();
$streams = new class ($psr17) implements ThrowableStreamFactoryInterface {
    /**
     * Stores the application's PSR-17 factory.
     *
     * @param StreamFactoryInterface $streams Creates public response body streams.
     */
    public function __construct(private StreamFactoryInterface $streams)
    {
    }

    /**
     * Returns controlled JSON directly, independently of diagnostic reporting.
     *
     * @param Throwable $throwable The private failure whose details stay undisclosed.
     *
     * @return StreamInterface A public JSON body without echo or output buffering.
     */
    public function createStreamFromThrowable(Throwable $throwable): StreamInterface
    {
        return $this->streams->createStream(
            json_encode(['error' => 'Service unavailable'], JSON_THROW_ON_ERROR)
        );
    }
};

$responses = new ThrowableResponseFactory($streams, $psr17);
$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $responses);

$downstream = new class () implements RequestHandlerInterface {
    /**
     * Simulates a private application failure with an HTTP error code.
     *
     * @param ServerRequestInterface $request The incoming request.
     *
     * @return ResponseInterface The error response produced by middleware.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw new RuntimeException('Private credentials', 503);
    }
};

$response = $middleware->process($psr17->createServerRequest('GET', '/'), $downstream)
    ->withHeader('Content-Type', 'application/json');

printf("HTTP %d | %s\n", $response->getStatusCode(), $response->getHeaderLine('Content-Type'));
echo (string) $response->getBody() . PHP_EOL;
printf("Private reports: %d\n", count($privateDiagnostics));
