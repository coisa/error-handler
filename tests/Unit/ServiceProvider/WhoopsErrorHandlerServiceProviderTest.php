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
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\WhoopsErrorHandlerServiceProvider;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\CallableFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Inspector\InspectorInterface;

use function FastForward\Container\container;

/**
 * Exercises the optional Whoops profile with the real FastForward composition.
 *
 * @internal
 */
#[CoversClass(WhoopsErrorHandlerServiceProvider::class)]
#[UsesClass(ErrorHandlerServiceProvider::class)]
#[UsesClass(ErrorHandlerMiddlewareFactory::class)]
#[UsesClass(WhoopsThrowableResponseFactory::class)]
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

    /** Declares only the renderer profile, leaving lifecycle composition to the application. */
    public function testProfileDeclaresOnlyItsRendererServices(): void
    {
        $provider = new WhoopsErrorHandlerServiceProvider();

        self::assertEqualsCanonicalizing([
            PrettyPageHandler::class,
            WhoopsThrowableResponseFactory::class,
            ThrowableResponseFactoryInterface::class,
        ], array_keys($provider->getFactories()));
        self::assertSame([], $provider->getExtensions());
    }

    /** Resolves the class-string profile and keeps sequential default pages independent. */
    public function testDefaultProfileSelectsPureHtmlWithFreshBodies(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $services = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            new WhoopsErrorHandlerServiceProvider(),
            $this->application($reporter),
        ));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);

        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $renderer);
        self::assertSame($renderer, $services->get(WhoopsThrowableResponseFactory::class));
        $htmlRenderer = $services->get(WhoopsThrowableResponseFactory::class);
        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $htmlRenderer);

        $first = new \RuntimeException(base64_encode('first default provider diagnostic'));
        $second = new \RuntimeException(base64_encode('second default provider diagnostic'));
        $one = $renderer->createResponseFromThrowable($first);
        $two = $renderer->createResponseFromThrowable($second);

        self::assertNotSame($one->getBody(), $two->getBody());
        self::assertStringContainsString($first->getMessage(), (string) $one->getBody());
        self::assertStringContainsString($second->getMessage(), (string) $two->getBody());
        self::assertStringNotContainsString($first->getMessage(), (string) $two->getBody());
    }

    /** The real provider selects pure HTML after one scoped middleware report. */
    public function testMiddlewareReportsOnceBeforeTheResolvedHtmlRenderer(): void
    {
        $original = new \RuntimeException(base64_encode('one private major request'), 503);
        $reports = [];
        $scope = $this->createMockForIntersectionOfInterfaces([
            ErrorHandlerInterface::class,
            ThrowableHandlerInterface::class,
        ]);
        self::assertInstanceOf(ErrorHandlerInterface::class, $scope);
        self::assertInstanceOf(ThrowableHandlerInterface::class, $scope);
        $scope->expects(self::once())->method('register');
        $scope->expects(self::once())->method('unregister');
        $scope->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original))
            ->willReturnCallback(
                /** @param \Throwable $event the original request reported before rendering */
                static function (\Throwable $event) use (&$reports): void {
                    $reports[] = $event;
                },
            )
        ;
        $services = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            new WhoopsErrorHandlerServiceProvider(),
            $this->application($scope, [
                PrettyPageHandler::class => new CallableFactory(
                    /**
                     * @param ContainerInterface $container the application resolver
                     *
                     * @return PrettyPageHandler the lazily selected page after reporting
                     */
                    static function (ContainerInterface $container) use (&$reports, $original): PrettyPageHandler {
                        self::assertSame([$original], $reports);

                        return new PrettyPageHandler();
                    },
                ),
            ]),
        ));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $renderer);
        $middleware = new ErrorHandlerMiddleware($scope, $renderer);
        $response = $middleware->process(
            (new Psr17Factory())->createServerRequest('GET', '/synthetic'),
            new ThrowingRequestHandler($original),
        );

        self::assertSame([$original], $reports);
        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString($original->getMessage(), (string) $response->getBody());
    }

    /** Reuses a lazily configured page while replacing sequential request diagnostics. */
    public function testApplicationPageServicePreservesConfigurationAndReplacesDiagnosticState(): void
    {
        $pages = [];
        $inspected = [];
        $calls = 0;
        $provider = new WhoopsErrorHandlerServiceProvider();
        $provider->getFactories();
        self::assertSame([], $provider->getExtensions());
        self::assertSame(0, $calls);

        $reporter = new CallableThrowableHandler(
            /** @param \Throwable $event the private synthetic diagnostic */
            static function (\Throwable $event): void {
                // This fixture deliberately has no public reporting output.
            },
        );
        $application = $this->application($reporter, [
            PrettyPageHandler::class => new CallableFactory(
                /**
                 * @param ContainerInterface $container the application resolver
                 *
                 * @return PrettyPageHandler the shared application-configured page
                 */
                static function (ContainerInterface $container) use (
                    &$pages,
                    &$inspected,
                    &$calls
                ): PrettyPageHandler {
                    ++$calls;
                    $page = new PrettyPageHandler();
                    $page->setPageTitle('Configured provider page');
                    $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
                    $page->addDataTableCallback(
                        'Synthetic identity',
                        /**
                         * @param InspectorInterface $inspector the current diagnostic inspector
                         *
                         * @return array<string, string> synthetic table data
                         */
                        static function (InspectorInterface $inspector) use (&$inspected): array {
                            $inspected[] = $inspector->getException();

                            return ['fixture' => 'current diagnostic inspected'];
                        },
                    );
                    $pages[] = $page;

                    return $page;
                },
            ),
        ]);
        $services = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            $provider,
            $application,
        ));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $renderer);
        self::assertSame(0, $calls);

        $first = new \RuntimeException(base64_encode('first configured provider diagnostic'));
        $second = new \RuntimeException(base64_encode('second configured provider diagnostic'));
        $oldValue = base64_encode('first synthetic request data');
        $newValue = base64_encode('second synthetic request data');
        $authorization = base64_encode('hidden synthetic authorization');
        $_SERVER['HTTP_AUTHORIZATION'] = $authorization;
        $_GET['fixture'] = $oldValue;
        $one = $renderer->createResponseFromThrowable($first);
        $_GET['fixture'] = $newValue;
        $two = $renderer->createResponseFromThrowable($second);

        self::assertSame(1, $calls);
        self::assertCount(1, $pages);
        self::assertSame($pages[0], $services->get(PrettyPageHandler::class));
        self::assertSame([$first, $second], $inspected);
        self::assertNotSame($one->getBody(), $two->getBody());
        self::assertSame('Configured provider page', $this->pageTitle((string) $one->getBody()));
        self::assertSame('Configured provider page', $this->pageTitle((string) $two->getBody()));
        self::assertStringContainsString($oldValue, (string) $one->getBody());
        self::assertStringContainsString($newValue, (string) $two->getBody());
        self::assertStringNotContainsString($oldValue, (string) $two->getBody());
        self::assertStringContainsString($second->getMessage(), (string) $two->getBody());
        self::assertStringNotContainsString($first->getMessage(), (string) $two->getBody());
        self::assertStringNotContainsString($authorization, (string) $one->getBody());
        self::assertStringNotContainsString($authorization, (string) $two->getBody());
    }

    /** Demonstrates why the selected renderer must override the separate base default. */
    public function testBaseFirstSelectsTheGenericDefault(): void
    {
        $reporter = new CallableThrowableHandler(
            /** @param \Throwable $event the synthetic private diagnostic */
            static function (\Throwable $event): void {
                // This fixture deliberately has no public reporting output.
            },
        );
        $services = container(
            ErrorHandlerServiceProvider::class,
            WhoopsErrorHandlerServiceProvider::class,
            $this->application($reporter),
        );
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);

        self::assertInstanceOf(ThrowableResponseFactory::class, $renderer);
    }

    /**
     * Distinguishes real container fallback from a returned incompatible page service.
     *
     * @param bool $throws whether page construction fails instead of returning an incompatible service
     */
    #[DataProvider('providePageResolutionFollowsContainerFallbackAndReturnTypesCases')]
    public function testPageResolutionFollowsContainerFallbackAndReturnTypes(bool $throws): void
    {
        $original = new \RuntimeException('Synthetic page resolution diagnostic');
        $failure = new \LogicException('Synthetic application page construction failure');
        $reports = [];
        $calls = 0;
        $reporter = new CallableThrowableHandler(
            /** @param \Throwable $event the unchanged private diagnostic */
            static function (\Throwable $event) use (&$reports): void {
                $reports[] = $event;
            },
        );
        $application = $this->application($reporter, [
            PrettyPageHandler::class => new CallableFactory(
                /**
                 * @param ContainerInterface $container the application resolver
                 *
                 * @return object the deliberately incompatible page in the nonthrowing case
                 *
                 * @throws \LogicException the unchanged application factory failure
                 */
                static function (ContainerInterface $container) use ($throws, $failure, &$calls): object {
                    ++$calls;

                    if ($throws) {
                        throw $failure;
                    }

                    return new \stdClass();
                },
            ),
        ]);
        $services = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            new WhoopsErrorHandlerServiceProvider(),
            $application,
        ));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(WhoopsThrowableResponseFactory::class, $renderer);
        self::assertSame(0, $calls);
        $caught = null;
        $response = null;

        try {
            $response = $renderer->createResponseFromThrowable($original);
        } catch (\Throwable $event) {
            $caught = $event;
        }

        self::assertSame([], $reports);
        self::assertSame(1, $calls);

        if ($throws) {
            // Aggregate fixes the binding map; FastForward can still autowire a default page.
            self::assertNull($caught);
            self::assertInstanceOf(ResponseInterface::class, $response);
            self::assertStringContainsString($original->getMessage(), (string) $response->getBody());
        } else {
            self::assertInstanceOf(\TypeError::class, $caught);
        }
    }

    /** @return array<string, array{bool}> independent page construction and type failures */
    public static function providePageResolutionFollowsContainerFallbackAndReturnTypesCases(): iterable
    {
        return ['factory failure' => [true], 'incompatible service' => [false]];
    }

    /**
     * Keeps cached process services inert and creates distinct native owners for HTTP scopes.
     */
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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
            $services = container(new AggregateServiceProvider(
                new ErrorHandlerServiceProvider(),
                new WhoopsErrorHandlerServiceProvider(),
                $this->application($reporter),
            ));
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
     */
    #[DataProvider('provideFailuresReportOriginalOnceAndRestoreCallbacksCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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
            $application = $this->application($reporter, [
                PrettyPageHandler::class => new CallableFactory(
                    /**
                     * @param ContainerInterface $container the application resolver
                     *
                     * @return PrettyPageHandler the page which fails before reading diagnostic source
                     */
                    static function (ContainerInterface $container) use ($failure, &$pageCalls): PrettyPageHandler {
                        ++$pageCalls;

                        return new class ($failure) extends PrettyPageHandler {
                            /** @var \LogicException the unchanged synthetic rendering failure */
                            private \LogicException $failure;

                            /** @param \LogicException $failure the exact application page failure */
                            public function __construct(\LogicException $failure)
                            {
                                parent::__construct();
                                $this->failure = $failure;
                            }

                            /**
                             * @return int this fixture never returns a normal handler result
                             *
                             * @throws \LogicException the unchanged failure inside Whoops rendering
                             */
                            public function handle(): int
                            {
                                throw $this->failure;
                            }
                        };
                    },
                ),
            ]);
            $services = container(new AggregateServiceProvider(
                new ErrorHandlerServiceProvider(),
                new WhoopsErrorHandlerServiceProvider(),
                $application,
            ));
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
        return ['private reporter' => ['reporter'], 'configured page rendering' => ['page']];
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
