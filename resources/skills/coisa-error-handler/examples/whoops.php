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
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Whoops\Handler\PrettyPageHandler;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

require_once __DIR__ . '/ThrowingRequestHandler.php';

foreach ([WhoopsThrowableResponseFactory::class, ReportingThrowableResponseFactory::class] as $class) {
    if (!class_exists($class)) {
        throw new RuntimeException('The selected library installation lacks ' . $class);
    }
}

if (!class_exists(PrettyPageHandler::class)) {
    throw new RuntimeException('This optional application route needs filp/whoops:^2.18.4.');
}

// Running this program is an explicit development/HTML choice, never a request flag.
$configuration = ['page_title' => 'Synthetic application diagnostics'];
$reporter = new RecordingThrowableHandler();
$http = new Psr17Factory();
$failure = new RuntimeException('<private synthetic HTML diagnostic>', 503);
$pageCalls = 0;

$html = new WhoopsThrowableResponseFactory(
    $http,
    $http,
    /** @return PrettyPageHandler an explicitly configured development page */
    static function () use ($configuration, &$pageCalls): PrettyPageHandler {
        ++$pageCalls;
        $page = new PrettyPageHandler();

        $page->setPageTitle($configuration['page_title']);
        $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
        $page->hideSuperglobalKey('_COOKIE', 'session');
        $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

        return $page;
    },
);

if (0 !== $pageCalls) {
    throw new RuntimeException('Construction must not invoke the page callback.');
}

$rendering = new ReportingThrowableResponseFactory($reporter, $html);

$middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $rendering);

$entryLevel = ob_get_level();
$response = $middleware->process(
    $http->createServerRequest('GET', 'http://example.test/development-html'),
    new ThrowingRequestHandler($failure),
);

if ([$failure] !== $reporter->getThrowables() || 1 !== $pageCalls || $entryLevel !== ob_get_level()) {
    throw new RuntimeException('Expected one original report and page without changing the buffer stack.');
}

if (503 !== $response->getStatusCode() || $failure->getMessage() === $response->getReasonPhrase()) {
    throw new RuntimeException('Expected the HTTP status and a PSR-17 reason independent of diagnostic text.');
}

if ('text/html; charset=UTF-8' !== $response->getHeaderLine('Content-Type')
    || 'no-store' !== $response->getHeaderLine('Cache-Control')) {
    throw new RuntimeException('Expected non-cacheable development HTML.');
}

if (false === strpos((string) $response->getBody(), '&lt;private synthetic HTML diagnostic&gt;')) {
    throw new RuntimeException('Expected the escaped development diagnostic.');
}

// Hiding selected fields is not complete redaction. The application still owns HTTP emission.
echo 'Whoops HTML: explicit development page and one original report passed' . PHP_EOL;
