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

namespace CoiSA\ErrorHandler\Test\Unit\ServiceProvider;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;

use function FastForward\Container\container;

/**
 * Exercises the optional Whoops profile with the real FastForward composition.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider
 *
 * @uses \CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider
 * @uses \CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory
 * @uses \CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory
 * @uses \CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory
 */
final class WhoopsErrorHandlerServiceProviderTest extends TestCase
{
    /** @var array<string, array{bool, mixed}> caller values restored without rendering them */
    private array $globals = [];

    /** Replaces ambient diagnostic tables with synthetic fixture values. */
    protected function setUp(): void
    {
        foreach (['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER', '_ENV'] as $name) {
            $this->globals[$name] = [\array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
            $GLOBALS[$name] = [];
        }
    }

    /** Restores the caller's values and absence of optional globals. */
    protected function tearDown(): void
    {
        foreach ($this->globals as $name => [$existed, $value]) {
            if ($existed) {
                $GLOBALS[$name] = $value;
            } else {
                unset($GLOBALS[$name]);
            }
        }
    }

    /** Resolves the class-string profile and keeps sequential default pages independent. */
    public function testDefaultProfileSelectsReportingAndFreshHtml(): void
    {
        $reports = [];
        $reporter = new CallableThrowableHandler(
            /** @param \Throwable $event the original private diagnostic */
            static function (\Throwable $event) use (&$reports): void {
                $reports[] = $event;
            },
        );
        $services = container(WhoopsErrorHandlerServiceProvider::class, $this->application($reporter));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);

        self::assertInstanceOf(ReportingThrowableResponseFactory::class, $renderer);
        self::assertSame($renderer, $services->get(ReportingThrowableResponseFactory::class));
        $htmlRenderer = $services->get(WhoopsThrowableResponseFactory::class);
        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $htmlRenderer);
        self::assertSame([], $reports);

        $first = new \RuntimeException(base64_encode('first default provider diagnostic'));
        $second = new \RuntimeException(base64_encode('second default provider diagnostic'));
        $one = $renderer->createResponseFromThrowable($first);
        $two = $renderer->createResponseFromThrowable($second);

        self::assertSame([$first, $second], $reports);
        self::assertNotSame($one->getBody(), $two->getBody());
        self::assertStringContainsString($first->getMessage(), (string) $one->getBody());
        self::assertStringContainsString($second->getMessage(), (string) $two->getBody());
        self::assertStringNotContainsString($first->getMessage(), (string) $two->getBody());
    }

    /** Keeps configured callbacks lazy and creates two distinct customized pages. */
    public function testCustomPageFactoryRunsOncePerRender(): void
    {
        $pages = [];
        $calls = 0;
        $provider = new WhoopsErrorHandlerServiceProvider(
            /** @return PrettyPageHandler a new application-configured page */
            static function () use (&$pages, &$calls): PrettyPageHandler {
                ++$calls;
                $page = new PrettyPageHandler();
                $page->setPageTitle('Configured provider page');
                $pages[] = $page;

                return $page;
            },
        );
        $provider->getFactories();
        self::assertSame([], $provider->getExtensions());
        self::assertSame(0, $calls);

        $services = container($provider, $this->application(new CallableThrowableHandler(
            /** @param \Throwable $event the private synthetic diagnostic */
            static function (\Throwable $event): void {
                // This fixture deliberately has no public reporting output.
            },
        )));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(ReportingThrowableResponseFactory::class, $renderer);
        self::assertSame(0, $calls);

        $one = $renderer->createResponseFromThrowable(new \RuntimeException('Synthetic first request'));
        $two = $renderer->createResponseFromThrowable(new \RuntimeException('Synthetic second request'));

        self::assertSame(2, $calls);
        self::assertNotSame($pages[0], $pages[1]);
        self::assertSame('Configured provider page', $this->pageTitle((string) $one->getBody()));
        self::assertSame('Configured provider page', $this->pageTitle((string) $two->getBody()));
    }

    /** Preserves a callable string even when the application defines the same service ID. */
    public function testCallableStringIsNotResolvedAsAServiceId(): void
    {
        $id = self::class . '::configuredPage';
        $conflictingService = new \stdClass();
        $reporter = new CallableThrowableHandler(
            /** @param \Throwable $event the synthetic private diagnostic */
            static function (\Throwable $event): void {
                // This fixture deliberately has no public reporting output.
            },
        );
        $application = $this->application($reporter, [
            $id => new ServiceFactory($conflictingService),
        ]);
        $services = container(new WhoopsErrorHandlerServiceProvider($id), $application);
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(ReportingThrowableResponseFactory::class, $renderer);

        $response = $renderer->createResponseFromThrowable(new \RuntimeException('Synthetic callable request'));

        self::assertSame('Callable provider page', $this->pageTitle((string) $response->getBody()));
        self::assertSame($conflictingService, $services->get($id));
    }

    /**
     * Keeps cached process services inert and creates distinct native owners for HTTP scopes.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFreshMiddlewareScopesRestoreCallerCallbacks(): void
    {
        $reports = [];
        $active = [];
        $errorCallback = static fn(): bool => true;
        $exceptionCallback = static function (\Throwable $event): void {
            // The fixture preserves this caller callback without invoking it.
        };
        set_error_handler($errorCallback);
        set_exception_handler($exceptionCallback);

        try {
            $reporter = new CallableThrowableHandler(
                /** @param \Throwable $event the unchanged private diagnostic */
                static function (\Throwable $event) use (&$reports): void {
                    $reports[] = $event;
                },
            );
            $services = container(WhoopsErrorHandlerServiceProvider::class, $this->application($reporter));
            $process = $services->get(ErrorHandlerInterface::class);
            $factory = $services->get(ErrorHandlerMiddlewareFactory::class);
            self::assertInstanceOf(ErrorHandler::class, $process);
            self::assertSame($process, $services->get(ErrorHandlerInterface::class));
            self::assertInstanceOf(ErrorHandlerMiddlewareFactory::class, $factory);
            $this->assertCallerCallbacks($errorCallback, $exceptionCallback);

            $one = $factory($services);
            $two = $factory($services);
            self::assertNotSame($one, $two);
            $messages = new Psr17Factory();
            foreach ([$one, $two] as $middleware) {
                $expected = $messages->createResponse();
                $next = $this->createMock(RequestHandlerInterface::class);
                $next->expects(self::once())->method('handle')->willReturnCallback(
                    /** @return ResponseInterface the successful route response */
                    static function () use (&$active, $errorCallback, $expected): ResponseInterface {
                        $callback = set_error_handler($errorCallback);
                        restore_error_handler();
                        self::assertNotNull($callback);
                        $active[] = $callback;

                        return $expected;
                    },
                );
                $response = $middleware->process(
                    $messages->createServerRequest('GET', '/synthetic'),
                    $next,
                );
                self::assertSame($expected, $response);
                $this->assertCallerCallbacks($errorCallback, $exceptionCallback);
            }

            self::assertSame([], $reports);
            self::assertNotSame($active[0], $active[1]);
            self::assertNotSame([$process, 'handlePhpError'], $active[0]);
        } finally {
            restore_exception_handler();
            restore_error_handler();
        }
    }

    /**
     * Propagates profile collaborators unchanged while restoring the native caller scope.
     *
     * @param string $stage the collaborator which throws
     *
     * @dataProvider provideFailuresReportOriginalOnceAndRestoreCallbacksCases
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFailuresReportOriginalOnceAndRestoreCallbacks(string $stage): void
    {
        $original = new \RuntimeException('Synthetic failed scope');
        $failure = new \LogicException('Synthetic collaborator failure');
        $reports = [];
        $pageCalls = 0;
        $errorCallback = static fn(): bool => true;
        $exceptionCallback = static function (\Throwable $event): void {
            // The fixture preserves this caller callback without invoking it.
        };
        set_error_handler($errorCallback);
        set_exception_handler($exceptionCallback);

        try {
            $reporter = new CallableThrowableHandler(
                /** @param \Throwable $event the unchanged private diagnostic */
                static function (\Throwable $event) use ($stage, $failure, &$reports): void {
                    $reports[] = $event;

                    if ('reporter' === $stage) {
                        throw $failure;
                    }
                },
            );
            $profile = new WhoopsErrorHandlerServiceProvider(
                /** @return PrettyPageHandler the callback fails only in the page stage */
                static function () use ($stage, $failure, &$pageCalls): PrettyPageHandler {
                    ++$pageCalls;

                    if ('page' === $stage) {
                        throw $failure;
                    }

                    return new PrettyPageHandler();
                },
            );
            $services = container($profile, $this->application($reporter));
            $factory = $services->get(ErrorHandlerMiddlewareFactory::class);
            self::assertInstanceOf(ErrorHandlerMiddlewareFactory::class, $factory);
            $messages = new Psr17Factory();
            $caught = null;

            try {
                $factory($services)->process(
                    $messages->createServerRequest('GET', '/synthetic'),
                    $this->failingRoute($original),
                );
            } catch (\Throwable $event) {
                $caught = $event;
            }

            self::assertSame($failure, $caught);
            self::assertSame([$original], $reports);
            self::assertSame('reporter' === $stage ? 0 : 1, $pageCalls);
            $this->assertCallerCallbacks($errorCallback, $exceptionCallback);
        } finally {
            restore_exception_handler();
            restore_error_handler();
        }
    }

    /** @return array<string, array{string}> independently failing collaborators */
    public static function provideFailuresReportOriginalOnceAndRestoreCallbacksCases(): iterable
    {
        return ['private reporter' => ['reporter'], 'page callback' => ['page']];
    }

    /** @return PrettyPageHandler the real callable-string fixture page */
    public static function configuredPage(): PrettyPageHandler
    {
        $page = new PrettyPageHandler();
        $page->setPageTitle('Callable provider page');

        return $page;
    }

    /**
     * Supplies only the application's HTTP implementations, private reporter and explicit extras.
     *
     * @param ThrowableHandlerInterface                          $reporter the private diagnostic collaborator
     * @param array<string, callable(ContainerInterface): mixed> $extra    additional application entries
     *
     * @return ArrayServiceProvider the application-owned definitions
     */
    private function application(ThrowableHandlerInterface $reporter, array $extra = []): ArrayServiceProvider
    {
        $messages = new Psr17Factory();

        return new ArrayServiceProvider(array_merge([
            ResponseFactoryInterface::class => new ServiceFactory($messages),
            StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
            ThrowableHandlerInterface::class => new ServiceFactory($reporter),
        ], $extra));
    }

    /**
     * Returns the actual document title rather than text found in a source excerpt.
     *
     * @param string $html the real Whoops page
     *
     * @return string the escaped title content
     */
    private function pageTitle(string $html): string
    {
        if (1 !== preg_match('~<title>(.*?)</title>~s', $html, $matches)) {
            self::fail('The Whoops response must contain an actual document title.');
        }

        return trim($matches[1]);
    }

    /**
     * Probes and immediately restores both caller callbacks before assertions can fail.
     *
     * @param callable $errorCallback     the caller PHP-error callback
     * @param callable $exceptionCallback the caller exception callback
     */
    private function assertCallerCallbacks(callable $errorCallback, callable $exceptionCallback): void
    {
        $currentError = set_error_handler($errorCallback);
        restore_error_handler();
        $currentException = set_exception_handler($exceptionCallback);
        restore_exception_handler();

        self::assertSame($errorCallback, $currentError);
        self::assertSame($exceptionCallback, $currentException);
    }

    /**
     * Creates a failing application route without registering native callbacks.
     *
     * @param \Throwable $failure the original route failure
     *
     * @return RequestHandlerInterface the synthetic route
     */
    private function failingRoute(\Throwable $failure): RequestHandlerInterface
    {
        return new class ($failure) implements RequestHandlerInterface {
            /** @var \Throwable the unchanged route failure */
            private \Throwable $failure;

            /**
             * @param \Throwable $failure the synthetic original
             */
            public function __construct(\Throwable $failure)
            {
                $this->failure = $failure;
            }

            /**
             * @param ServerRequestInterface $request the synthetic request
             *
             * @return ResponseInterface this fixture never returns a normal response
             *
             * @throws \Throwable the unchanged original
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };
    }
}
