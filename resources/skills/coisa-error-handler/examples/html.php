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
use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/bootstrap.php';

$mode = $argv[2] ?? 'public';
if (!in_array($mode, ['public', 'debug'], true)) {
    throw new InvalidArgumentException('Choose public or debug mode.');
}

// This boolean belongs to trusted application configuration, never the request.
$debug = 'debug' === $mode;
$reports = [];
$errors = new ErrorHandler(
    /** @param Throwable $error the unchanged original diagnostic delivered privately */
    static function (Throwable $error) use (&$reports): void {
        $reports[] = $error; // Replace with the application's private diagnostic transport.
    },
);

$messages = new Psr17Factory();
$failure = new RuntimeException('Private synthetic HTML diagnostic', 503);
$request = $messages->createServerRequest('GET', 'http://example.test/html');
$renderer = new HtmlThrowableResponseFactory($messages, $messages, $debug);

// An explicit pipeline owns reporting; the renderer only builds a response.
$errors->handleThrowable($failure);
$response = $renderer->createResponseFromThrowable($failure, $request);

if ([$failure] !== $reports || 503 !== $response->getStatusCode()) {
    throw new RuntimeException('Expected one original report and the HTTP error status.');
}
if ('text/html; charset=UTF-8' !== $response->getHeaderLine('Content-Type')
    || ($debug && 'no-store' !== $response->getHeaderLine('Cache-Control'))) {
    throw new RuntimeException('Expected HTML and a no-store policy for debug.');
}
if ($debug !== str_contains((string) $response->getBody(), $failure->getMessage())) {
    throw new RuntimeException('Only explicit debug may disclose the diagnostic.');
}
if ($failure->getMessage() === $response->getReasonPhrase()) {
    throw new RuntimeException('Diagnostic text must not become the reason phrase.');
}

// Applications own emission; this fixture prints no diagnostic HTML.
echo 'HTTP HTML ' . $mode . ': Symfony rendering and one original report passed' . PHP_EOL;
