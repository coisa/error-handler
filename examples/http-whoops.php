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
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;

require __DIR__ . '/bootstrap.php';

$messages = new Psr17Factory();
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'development'], true), 'Choose public or development mode.');

// This CLI fixture represents trusted configuration for an explicitly selected HTML pipeline.
// A request header or query parameter must never enable development diagnostics.
$debug = 'development' === $mode;
$pageCalls = 0;
$reports = [];
$original = new RuntimeException('<synthetic-development-detail>', 503);

$reporter = new CallableThrowableHandler(
    /** @param Throwable $error the unchanged diagnostic delivered privately */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error; // Replace this recording sink with the application's private transport.
    },
);

$renderer = new JsonThrowableResponseFactory($messages, $messages);

if ($debug) {
    $renderer = new WhoopsThrowableResponseFactory(
        $messages,
        $messages,
        /** @return PrettyPageHandler a new page using the application's explicit field-hiding policy */
        static function () use (&$pageCalls): PrettyPageHandler {
            ++$pageCalls;
            $page = new PrettyPageHandler();

            $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
            $page->hideSuperglobalKey('_COOKIE', 'session');
            $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

            return $page;
        },
    );
}

exampleCheck(0 === $pageCalls, 'Construction must not create a page.');

$responses = new ReportingThrowableResponseFactory($reporter, $renderer);

$errors = new ErrorHandler($reporter);

$middleware = new ErrorHandlerMiddleware($errors, $responses);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic application failure */
    private Throwable $original;

    /** @param Throwable $original the unchanged failure thrown by the route */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * @param ServerRequestInterface $request the synthetic application request
     *
     * @return ResponseInterface this failing route returns no normal response
     *
     * @throws Throwable the unchanged application failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$bufferLevel = ob_get_level();
$response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);

exampleCheck([$original] === $reports, 'Expected one private report of the original failure.');
exampleCheck(503 === $response->getStatusCode(), 'Expected the original integer HTTP status.');
exampleCheck($bufferLevel === ob_get_level(), 'The renderer changed the caller buffer stack.');
exampleCheck($original->getMessage() !== $response->getReasonPhrase(), 'The diagnostic became a reason phrase.');

if ($debug) {
    exampleCheck(1 === $pageCalls, 'Expected one fresh configured page.');
    exampleCheck('text/html; charset=UTF-8' === $response->getHeaderLine('Content-Type'), 'Expected HTML.');
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
    exampleCheck(
        false !== strpos((string) $response->getBody(), '&lt;synthetic-development-detail&gt;'),
        'Expected an escaped diagnostic in the development page.',
    );
} else {
    exampleCheck(0 === $pageCalls, 'Public mode must not construct a Whoops page.');
    exampleCheck('application/json' === $response->getHeaderLine('Content-Type'), 'Expected public JSON.');
    exampleCheck('{"error":"Request failed"}' === (string) $response->getBody(), 'Expected the generic public body.');
}

// No response is emitted. Hiding selected keys is not complete redaction or production authorization.
echo 'HTTP Whoops ' . $mode . ': explicit renderer selection and one private report passed' . PHP_EOL;
