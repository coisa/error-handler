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
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Exception\Frame;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Inspector\InspectorInterface;

$autoload = $argv[1] ?? '';

require $autoload;

// Whoops receives synthetic tables, never the agent's inherited application data.
foreach (['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER', '_ENV'] as $name) {
    $GLOBALS[$name] = [];
}

/**
 * Creates a native frame in whichever source PHP is actually executing.
 *
 * @return RuntimeException the original diagnostic with an actual source and trace
 */
$createFailure = static fn(): RuntimeException => new RuntimeException('Synthetic source diagnostic', 503);

$original = $createFailure();
$trace = $original->getTrace();
$received = [];
$inspected = [];
$frames = [];
$reporter = new CallableThrowableHandler(
    /** @param Throwable $event the original diagnostic or a real application warning */
    static function (Throwable $event) use (&$received): void {
        $received[] = $event;
    },
);
$messages = new Psr17Factory();
$renderer = new WhoopsThrowableResponseFactory(
    $messages,
    $messages,
    /** @return PrettyPageHandler a page observing the actual exception identity */
    static function () use (&$inspected, &$frames): PrettyPageHandler {
        $page = new PrettyPageHandler();
        $page->addDataTableCallback(
            'Synthetic source identity',
            /** @return array<string, string> controlled page data without changing diagnostic frames */
            static function (InspectorInterface $inspector) use (&$inspected, &$frames): array {
                $inspected[] = $inspector->getException();

                /** @var Frame $frame an actual frame exposed by Whoops' collection */
                foreach ($inspector->getFrames() as $frame) {
                    $frames[] = [$frame->getFile(), $frame->getLine()];
                }

                return ['fixture' => 'native source'];
            },
        );

        return $page;
    },
);
$middleware = new ErrorHandlerMiddleware(
    new ErrorHandler($reporter),
    $renderer,
);
$next = new class ($original) implements RequestHandlerInterface {
    /** @var Throwable the unchanged downstream diagnostic */
    private Throwable $failure;

    /** @param Throwable $failure the failure created by native PHP source execution */
    public function __construct(Throwable $failure)
    {
        $this->failure = $failure;
    }

    /**
     * Emits one legitimate warning before the downstream failure.
     *
     * @param ServerRequestInterface $request the synthetic incoming request
     *
     * @return ResponseInterface this failing route returns no response
     *
     * @throws Throwable the exact original failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        trigger_error('Legitimate application warning', E_USER_WARNING);

        throw $this->failure;
    }
};

/** @return bool a temporary probe that leaves the previous callback's mask intact */
$probeError = static fn(): bool => false;

/**
 * @param Throwable $event an unexpected exception reaching the temporary probe
 *
 * @throws LogicException the probe must never handle a native failure
 */
$probeException = static function (Throwable $event): void {
    throw new LogicException('The callback probe must not handle a failure.', 0, $event);
};
$previousError = set_error_handler($probeError);
restore_error_handler();
$previousException = set_exception_handler($probeException);
restore_exception_handler();
$entry = ob_get_level();
ob_start();
echo 'caller-owned bytes';

try {
    $response = $middleware->process($messages->createServerRequest('GET', '/synthetic'), $next);
    $bufferSame = $entry + 1 === ob_get_level() && 'caller-owned bytes' === ob_get_contents();
    $currentError = set_error_handler($probeError);
    restore_error_handler();
    $currentException = set_exception_handler($probeException);
    restore_exception_handler();
} finally {
    while (ob_get_level() > $entry) {
        ob_end_clean();
    }
}

echo json_encode([
    'source' => $original->getFile(),
    'frameSource' => $trace[0]['file'] ?? null,
    'traceSame' => $trace === $original->getTrace(),
    'inspectedFrameSame' => in_array([$original->getFile(), $original->getLine()], $frames, true),
    'originalSame' => [$original] === $inspected && ($received[1] ?? null) === $original,
    'reports' => array_map(
        /**
         * @param Throwable $event a real delivered diagnostic
         *
         * @return array{string, null|int} message and native warning severity
         */
        static fn(Throwable $event): array => [
            $event->getMessage(),
            $event instanceof ErrorException ? $event->getSeverity() : null,
        ],
        $received,
    ),
    'status' => $response->getStatusCode(),
    'contentType' => $response->getHeaderLine('Content-Type'),
    'cacheControl' => $response->getHeaderLine('Cache-Control'),
    'htmlContainsOriginal' =>   str_contains((string) $response->getBody(), $original->getMessage()),
    'bufferSame' => $bufferSame && $entry === ob_get_level(),
    'callbacksSame' => $previousError === $currentError && $previousException === $currentException,
], JSON_THROW_ON_ERROR) . "\n";
