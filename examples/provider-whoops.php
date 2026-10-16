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
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;

use function FastForward\Config\config;
use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

exampleCheck(
    PHP_VERSION_ID >= 80300 && function_exists('FastForward\Container\container'),
    'Install fast-forward/container:^1.9 in the application and use PHP 8.3 or newer.',
);

// FastForward integration requires PHP 8.3+ and an application runtime dependency.
// Selecting this profile deliberately chooses diagnostic HTML; no request enables it.
$mode = $argv[1] ?? 'default';
exampleCheck(in_array($mode, ['default', 'configured'], true), 'Choose default or configured mode.');

$messages = new Psr17Factory();
$reports = [];
$pageCalls = 0;

$privateReporter = new CallableThrowableHandler(
    /** @param Throwable $event the unchanged application failure delivered privately */
    static function (Throwable $event) use (&$reports): void {
        $reports[] = $event; // Replace with the application's private log transport.
    },
);

$application = new ArrayServiceProvider([
    ResponseFactoryInterface::class => new ServiceFactory($messages),
    StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
    ThrowableHandlerInterface::class => new ServiceFactory($privateReporter),
]);

if ('configured' === $mode) {
    $settings = config([
        'development' => [
            'page_title' => 'Synthetic application diagnostics',
        ],
    ]);

    $profile = new WhoopsErrorHandlerServiceProvider(
        /** @return PrettyPageHandler a new page configured through Whoops' public API */
        static function () use ($settings, &$pageCalls): PrettyPageHandler {
            ++$pageCalls;
            $page = new PrettyPageHandler();

            $page->setPageTitle($settings->get('development.page_title'));
            $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
            $page->hideSuperglobalKey('_COOKIE', 'session');
            $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

            return $page;
        },
    );

    $services = container($profile, $application);
} else {
    $services = container(WhoopsErrorHandlerServiceProvider::class, $application);
}

$middlewareFactory = $services->get(ErrorHandlerMiddlewareFactory::class);
exampleCheck($middlewareFactory instanceof ErrorHandlerMiddlewareFactory, 'Expected the fresh-scope factory.');

$firstScope = $middlewareFactory($services);
$secondScope = $middlewareFactory($services);

exampleCheck(0 === $pageCalls, 'Discovery and scope construction must not render a page.');
exampleCheck($firstScope !== $secondScope, 'Each HTTP scope needs a new middleware and handler.');

$failures = [
    new RuntimeException(base64_encode('First synthetic provider diagnostic'), 503),
    new RuntimeException(base64_encode('Second synthetic provider diagnostic'), 404),
];

/**
 * Creates a synthetic route for the selected application failure.
 *
 * @param Throwable $failure the original failure for this HTTP scope
 *
 * @return RequestHandlerInterface the failing application route
 */
function exampleWhoopsRoute(Throwable $failure): RequestHandlerInterface
{
    return new class ($failure) implements RequestHandlerInterface {
        /** @var Throwable the unchanged synthetic route failure */
        private Throwable $failure;

        /** @param Throwable $failure the application error thrown in this request */
        public function __construct(Throwable $failure)
        {
            $this->failure = $failure;
        }

        /**
         * @param ServerRequestInterface $request the synthetic application request
         *
         * @return ResponseInterface this route never produces a normal response
         *
         * @throws Throwable the unchanged application failure
         */
        public function handle(ServerRequestInterface $request): ResponseInterface
        {
            throw $this->failure;
        }
    };
}

$first = $firstScope->process(
    $messages->createServerRequest('GET', '/first'),
    exampleWhoopsRoute($failures[0]),
);

$second = $secondScope->process(
    $messages->createServerRequest('GET', '/second'),
    exampleWhoopsRoute($failures[1]),
);

exampleCheck($failures === $reports, 'Each original must reach the private reporter once.');
exampleCheck(503 === $first->getStatusCode() && 404 === $second->getStatusCode(), 'Expected original HTTP codes.');
exampleCheck('text/html; charset=UTF-8' === $first->getHeaderLine('Content-Type'), 'Expected HTML.');
exampleCheck('no-store' === $first->getHeaderLine('Cache-Control'), 'Diagnostics must not be cached.');
exampleCheck(
    false === strpos((string) $second->getBody(), $failures[0]->getMessage()),
    'The next page must not retain the first diagnostic.',
);

if ('configured' === $mode) {
    exampleCheck(2 === $pageCalls, 'Expected one configured page per render.');
    exampleCheck(
        false !== strpos((string) $first->getBody(), '<title>Synthetic application diagnostics</title>'),
        'Expected the application-configured title.',
    );
}

// No HTTP emission. Hiding selected keys is not comprehensive redaction.
echo 'Whoops provider ' . $mode . ': lazy pages, fresh scopes and private reporting passed' . PHP_EOL;
