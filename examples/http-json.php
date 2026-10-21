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
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ProblemNormalizer;
use Symfony\Component\Serializer\Serializer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

$messages = new Psr17Factory();
$mode = $argv[1] ?? 'public';
exampleCheck(in_array($mode, ['public', 'debug'], true), 'Choose the public or debug example mode.');

// This explicit CLI fixture choice stands in for trusted application configuration.
$debug = 'debug' === $mode;

/** @return RuntimeException a synthetic downstream failure with a native call frame */
$createFailure = static fn(): RuntimeException => new RuntimeException('Private synthetic upstream details', 503);

$original = $createFailure();
$reports = [];

$reporter = new CallableThrowableHandler(
    /**
     * Record the original privately without printing a public diagnostic.
     *
     * @param Throwable $error the original downstream failure
     */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error; // Replace this recording sink with the application's private transport.
    },
);

$errors = new ErrorHandler($reporter);

$serializer = new Serializer([new ProblemNormalizer(true)], [new JsonEncoder()]);

$json = new JsonThrowableResponseFactory($messages, $messages, $serializer, $debug);

$middleware = new ErrorHandlerMiddleware($errors, $json);

$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the synthetic downstream failure */
    private Throwable $original;

    /**
     * Store the original event for the synthetic application route.
     *
     * @param Throwable $original the event the middleware will catch
     */
    public function __construct(Throwable $original)
    {
        $this->original = $original;
    }

    /**
     * Fail the route without printing diagnostics or emitting a response.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface no response is returned by this failing route
     *
     * @throws Throwable the original synthetic failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->original;
    }
};

$bufferLevel = ob_get_level();
$response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);

exampleCheck([$original] === $reports, 'Expected exactly one private report of the original event.');
exampleCheck(503 === $response->getStatusCode(), 'The integer HTTP error code was not preserved.');
exampleCheck(
    'application/problem+json' === $response->getHeaderLine('Content-Type'),
    'Expected a JSON Problem Details representation.',
);
$publicBody = '{"type":"about:blank","title":"Service Unavailable","status":503,"detail":"Request failed"}';

if ($debug) {
    $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    exampleCheck(
        $original->getMessage() === $payload['detail'] && [] !== $payload['trace'],
        'Explicit development metadata is missing.',
    );
    foreach ($payload['trace'] as $frame) {
        exampleCheck(
            [] === ($frame['args'] ?? []) && !array_key_exists('object', $frame),
            'Private trace arguments or objects must not be serialized.',
        );
    }
    exampleCheck('no-store' === $response->getHeaderLine('Cache-Control'), 'Debug details must not be cached.');
} else {
    exampleCheck(
        $publicBody === (string) $response->getBody(),
        'The generic JSON body must not contain the private diagnostic.',
    );
}
exampleCheck(
    $original->getMessage() !== $response->getReasonPhrase(),
    'The diagnostic message must not become an HTTP reason phrase.',
);
exampleCheck($bufferLevel === ob_get_level(), 'JSON composition unexpectedly changed the buffer stack.');

// The application owns response emission; this CLI recipe prints only its verification summary.
echo 'HTTP JSON ' . $mode . ': explicit payload, status preservation and one private report passed' . PHP_EOL;
