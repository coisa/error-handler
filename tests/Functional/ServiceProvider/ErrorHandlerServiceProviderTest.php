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

namespace CoiSA\ErrorHandler\Test\Functional\ServiceProvider;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Interop\Container\ServiceProviderInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

/**
 * Verifies real FastForward wiring, reporting failures and independent middleware scopes.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\ServiceProvider
 *
 * @internal
 */
final class ErrorHandlerServiceProviderTest extends TestCase
{
    /**
     * Verifies constructor autowiring preserves the provider's public interface aliases.
     */
    public function testHttpAdaptersAreAutowiredAndShareTheirInterfaceServices(): void
    {
        $received = [];
        $container = self::httpServices($received);
        self::assertInstanceOf(ThrowableStreamFactory::class, $container->get(ThrowableStreamFactory::class));
        self::assertSame(
            $container->get(ThrowableStreamFactory::class),
            $container->get(ThrowableStreamFactoryInterface::class)
        );
        self::assertInstanceOf(ThrowableResponseFactory::class, $container->get(ThrowableResponseFactory::class));
        self::assertSame(
            $container->get(ThrowableResponseFactory::class),
            $container->get(ThrowableResponseFactoryInterface::class)
        );
    }

    /**
     * Verifies explicit concrete adapter overrides remain effective under autowiring.
     */
    public function testApplicationCanOverrideAnAutowiredConcreteAdapter(): void
    {
        $renderer = new ThrowableStreamFactory(new Psr17Factory(), new CallableThrowableHandler(
            static function (): void {
                echo 'Application response';
            }
        ));
        $container = self::services([ThrowableStreamFactory::class => $renderer]);

        self::assertSame($renderer, $container->get(ThrowableStreamFactoryInterface::class));
        self::assertSame(
            'Application response',
            (string) $renderer->createStreamFromThrowable(new \RuntimeException())
        );
    }

    /**
     * Verifies missing required HTTP dependencies fail construction instead of returning partial middleware.
     */
    public function testMissingPsr17FactoriesPreventMiddlewareConstruction(): void
    {
        $container = self::services();
        $this->expectException(ContainerExceptionInterface::class);
        $container->get(ErrorHandlerMiddleware::class);
    }

    /**
     * Verifies required renderer interface failures cannot fall back to an autowired renderer.
     *
     * @param class-string $service The required renderer interface.
     * @param \Throwable $failure The synthetic construction failure.
     * @param class-string<\Throwable> $expectedException The expected propagated failure category.
     */
    #[DataProvider('requiredRendererFailures')]
    public function testRequiredRendererFailurePreventsMiddlewareConstruction(
        string $service,
        \Throwable $failure,
        string $expectedException
    ): void {
        $application = new ArrayServiceProvider([
            $service => static function () use ($failure): never {
                throw $failure;
            },
            ResponseFactoryInterface::class => static fn (): Psr17Factory => new Psr17Factory(),
            StreamFactoryInterface::class => static fn (): Psr17Factory => new Psr17Factory(),
        ]);
        $container = self::withApplication($application);
        $this->expectException($expectedException);

        $container->get(ErrorHandlerMiddleware::class);
    }

    /**
     * Provides PSR and application failures for both required renderer interfaces.
     *
     * @return iterable<string, array{class-string, \Throwable, class-string<\Throwable>}>
     *     Required renderer construction failures.
     */
    public static function requiredRendererFailures(): iterable
    {
        foreach (self::requiredReporterFailures() as $label => [$service, $failure, $expectedException]) {
            if ($service !== ErrorHandlerInterface::class) {
                continue;
            }
            foreach ([ThrowableStreamFactoryInterface::class, ThrowableResponseFactoryInterface::class] as $renderer) {
                yield $renderer . ' / ' . $label => [$renderer, $failure, $expectedException];
            }
        }
    }

    /**
     * Verifies provider bootstrap is lazy and preserves process-handler alias identity.
     */
    public function testProviderClassBootstrapIsLazyAndAliasesTheProcessHandler(): void
    {
        $before = self::callbacks();
        $container = container(ErrorHandlerServiceProvider::class);

        self::assertTrue($container->has(ErrorHandlerInterface::class));
        $handler = $container->get(ErrorHandlerInterface::class);
        self::assertInstanceOf(ErrorHandler::class, $handler);
        self::assertSame($handler, $container->get(ErrorHandler::class));
        self::assertSame($handler, $container->get(ErrorHandlerInterface::class));
        self::assertSame($before, self::callbacks());
        $handler->handleThrowable(new \RuntimeException('No reporter is configured.'));
    }

    /**
     * Verifies application definitions replace the silent default reporter.
     */
    public function testApplicationReporterOverridesTheDefaultAggregate(): void
    {
        $received = [];
        $reporter = new CallableThrowableHandler(static function (\Throwable $error) use (&$received): void {
            $received[] = $error;
        });
        $container = self::services([ThrowableHandlerInterface::class => $reporter]);
        $error = new \RuntimeException('Custom reporter');

        self::assertSame($reporter, $container->get(ThrowableHandlerInterface::class));
        $container->get(ErrorHandlerInterface::class)->handleThrowable($error);

        self::assertSame([$error], $received);
    }

    /**
     * Verifies required reporter failures prevent process and middleware construction.
     *
     * @param class-string $service The service identifier under test.
     * @param \Throwable $failure The synthetic failure to propagate.
     * @param class-string<\Throwable> $expectedException The expected propagated failure type.
     */
    #[DataProvider('requiredReporterFailures')]
    public function testRequiredReporterFailureCannotFallBackToASilentDefault(
        string $service,
        \Throwable $failure,
        string $expectedException
    ): void {
        $application = new class ($failure) implements ServiceProviderInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $failure The synthetic failure to propagate.
             */
            public function __construct(private \Throwable $failure)
            {
            }

            /**
             * Supplies application service definitions for the integration fixture.
             *
             * @return array<class-string, callable> Application service factories.
             */
            public function getFactories(): array
            {
                return [
                    ThrowableHandlerInterface::class => function (): never {
                        throw $this->failure;
                    },
                    ResponseFactoryInterface::class => static fn (): Psr17Factory => new Psr17Factory(),
                    StreamFactoryInterface::class => static fn (): Psr17Factory => new Psr17Factory(),
                ];
            }

            /**
             * Declares no application service extensions.
             *
             * @return array<string, callable> An empty extension map.
             */
            public function getExtensions(): array
            {
                return [];
            }
        };
        $container = self::withApplication($application);
        $this->expectException($expectedException);

        $container->get($service);
    }

    /**
     * Provides required-reporter failure categories for both lifecycle services.
     *
     * @return iterable<string, array{class-string, \Throwable, class-string<\Throwable>}>
     *     Required-reporter failure cases.
     */
    public static function requiredReporterFailures(): iterable
    {
        $failures = [
            'PSR construction failure' => [
                new class ('Reporter failed') extends \RuntimeException implements ContainerExceptionInterface {
                },
                ContainerExceptionInterface::class,
            ],
            'PSR missing dependency' => [
                new class ('Missing dependency') extends \RuntimeException implements NotFoundExceptionInterface {
                },
                ContainerExceptionInterface::class,
            ],
            'application failure' => [new \LogicException('Reporter failed'), \LogicException::class],
        ];
        foreach ([ErrorHandlerInterface::class, ErrorHandlerMiddleware::class] as $service) {
            foreach ($failures as $label => [$failure, $expectedException]) {
                yield $service . ' / ' . $label => [$service, $failure, $expectedException];
            }
        }
    }

    /**
     * Verifies callable reporting works without a PSR-14 dispatcher.
     */
    public function testAnOptionalCallableReporterIsComposedWithoutAnEventDispatcher(): void
    {
        $received = [];
        $container = self::services([
            CallableThrowableHandler::class => new CallableThrowableHandler(
                static function (\Throwable $error) use (&$received): void {
                    $received[] = $error;
                }
            ),
        ]);
        $error = new \RuntimeException('Callable reporter');

        $container->get(ErrorHandlerInterface::class)->handleThrowable($error);

        self::assertSame([$error], $received);
    }

    /**
     * Verifies autowired dispatch adapters deliver both supported event forms.
     */
    public function testOptionalPsr14DispatcherReceivesTheThrowableAndErrorEvent(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            /**
             * @var list<object> Events received by the recording dispatcher.
             */
            public array $events = [];

            /**
             * Records the event and returns the same object to the caller.
             *
             * @param object $event Event for the fixture.
             *
             * @return object The fixture result.
             */
            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };
        $container = self::services([EventDispatcherInterface::class => $dispatcher]);
        $error = new \RuntimeException('Dispatched');

        self::assertInstanceOf(DispatchThrowableHandler::class, $container->get(DispatchThrowableHandler::class));
        self::assertInstanceOf(
            DispatchErrorEventThrowableHandler::class,
            $container->get(DispatchErrorEventThrowableHandler::class)
        );

        $container->get(ErrorHandlerInterface::class)->handleThrowable($error);

        self::assertCount(2, $dispatcher->events);
        self::assertSame($error, $dispatcher->events[0]);
        self::assertInstanceOf(ErrorEvent::class, $dispatcher->events[1]);
        self::assertSame($error, $dispatcher->events[1]->getThrowable());
    }

    /**
     * Verifies optional application callbacks are injected and previous callbacks restored.
     */
    #[WithoutErrorHandler]
    public function testCustomPhpAndShutdownHandlersAreInjected(): void
    {
        $phpHandler = new class () implements PhpErrorHandlerInterface {
            /**
             * @var list<string> Messages received by the synthetic PHP error handler.
             */
            public array $messages = [];

            /**
             * Records a PHP error message without escalating the synthetic warning.
             *
             * @param int $errno Errno for the fixture.
             * @param string $message Message for the fixture.
             * @param string $file File for the fixture.
             * @param int $line Line for the fixture.
             */
            public function handlePhpError(int $errno, string $message, string $file, int $line): void
            {
                $this->messages[] = $message;
            }
        };
        $shutdowns = 0;
        $container = self::services([
            PhpErrorHandlerInterface::class => $phpHandler,
            ShutdownHandlerInterface::class => new CallableShutdownHandler(
                static function () use (&$shutdowns): void {
                    ++$shutdowns;
                }
            ),
        ]);
        $handler = $container->get(ErrorHandler::class);
        $before = self::callbacks();
        $handler->register();
        try {
            trigger_error('Custom PHP handler', E_USER_WARNING);
            $handler->handleShutdown();
        } finally {
            $handler->unregister();
        }

        self::assertSame(['Custom PHP handler'], $phpHandler->messages);
        self::assertSame(1, $shutdowns);
        self::assertSame($before, self::callbacks());
    }

    /**
     * Verifies factory invocations create independent handlers while process lookups remain cached.
     */
    public function testReusableFactoryCreatesFreshHandlersWithoutReplacingTheProcessService(): void
    {
        $container = self::services();
        $process = $container->get(ErrorHandlerInterface::class);
        $factory = $container->get(ErrorHandlerFactory::class);
        $first = $factory($container);
        $second = $factory($container);

        self::assertInstanceOf(ErrorHandler::class, $first);
        self::assertNotSame($first, $second);
        self::assertNotSame($first, $process);
        self::assertSame($process, $container->get(ErrorHandlerInterface::class));
    }

    /**
     * Verifies middleware restores the active process handler after a downstream failure.
     */
    #[WithoutErrorHandler]
    public function testMiddlewareRestoresTheAlreadyRegisteredProcessHandler(): void
    {
        $received = [];
        $container = self::httpServices($received);
        $process = $container->get(ErrorHandlerInterface::class);
        $process->register();
        try {
            $before = self::callbacks();
            $middleware = $container->get(ErrorHandlerMiddleware::class);
            self::assertSame($before, self::callbacks());
            $failure = new \RuntimeException('Private diagnostics', 503);
            $response = $middleware->process(self::request(), self::failsWith($failure));

            self::assertSame(503, $response->getStatusCode());
            self::assertSame('Internal Server Error', (string) $response->getBody());
            self::assertSame($before, self::callbacks());
            trigger_error('After middleware', E_USER_WARNING);
        } finally {
            $process->unregister();
        }

        self::assertCount(2, $received);
        self::assertSame($failure, $received[0]);
        self::assertInstanceOf(ErrorException::class, $received[1]);
        self::assertSame('After middleware', $received[1]->getMessage());
    }

    /**
     * Verifies nested middleware uses independent handlers and restores the process scope.
     */
    #[WithoutErrorHandler]
    public function testFactoryCreatesIndependentMiddlewareForNestedScopes(): void
    {
        $received = [];
        $container = self::httpServices($received);
        $factory = $container->get(ErrorHandlerMiddlewareFactory::class);
        $outer = $factory($container);
        $inner = $factory($container);
        $failure = new \RuntimeException('Nested');
        $downstream = new class ($inner, self::failsWith($failure)) implements RequestHandlerInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param ErrorHandlerMiddleware $inner Inner for the fixture.
             * @param RequestHandlerInterface $downstream Downstream for the fixture.
             */
            public function __construct(
                private ErrorHandlerMiddleware $inner,
                private RequestHandlerInterface $downstream
            ) {
            }

            /**
             * Executes the synthetic downstream behavior for the requested test route.
             *
             * @param ServerRequestInterface $request The synthetic incoming request.
             *
             * @return ResponseInterface The fixture result.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->inner->process($request, $this->downstream);
            }
        };
        $process = $container->get(ErrorHandlerInterface::class);
        $process->register();
        try {
            $before = self::callbacks();
            $response = $outer->process(self::request(), $downstream);
            self::assertSame(500, $response->getStatusCode());
            self::assertSame($before, self::callbacks());
        } finally {
            $process->unregister();
        }

        self::assertNotSame($outer, $inner);
        self::assertSame([$failure], $received);
    }

    /**
     * Verifies renderer failures propagate after restoring the active process callbacks.
     */
    #[WithoutErrorHandler]
    public function testRendererFailureRestoresTheProcessHandler(): void
    {
        $failure = new \RuntimeException('Renderer failed');
        $renderer = new class ($failure) implements ThrowableResponseFactoryInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $failure The synthetic failure to propagate.
             */
            public function __construct(private \Throwable $failure)
            {
            }

            /**
             * Throws the configured rendering failure to exercise callback restoration.
             *
             * @param \Throwable $throwable The throwable being rendered.
             *
             * @return ResponseInterface The fixture result.
             */
            public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
            {
                throw $this->failure;
            }
        };
        $container = self::services([ThrowableResponseFactoryInterface::class => $renderer]);
        $process = $container->get(ErrorHandlerInterface::class);
        $process->register();
        try {
            $before = self::callbacks();
            $middleware = $container->get(ErrorHandlerMiddleware::class);
            try {
                $middleware->process(self::request(), self::failsWith(new \RuntimeException('Original')));
                self::fail('The renderer failure must propagate.');
            } catch (\RuntimeException $caught) {
                self::assertSame($failure, $caught);
            }
            self::assertSame($before, self::callbacks());
        } finally {
            $process->unregister();
        }
    }

    /**
     * Composes library definitions with application service instances.
     *
     * @param array<class-string, object> $services Application service overrides.
     *
     * @return ContainerInterface The container with application definitions merged last.
     */
    private static function services(array $services = []): ContainerInterface
    {
        $application = new class ($services) implements ServiceProviderInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param array<class-string, object> $services Application service instances.
             */
            public function __construct(private array $services)
            {
            }

            /**
             * Supplies application service definitions for the integration fixture.
             *
             * @return array<class-string, callable> Application service factories.
             */
            public function getFactories(): array
            {
                return array_map(static fn (object $service): \Closure =>
                    static fn (): object => $service, $this->services);
            }

            /**
             * Declares no application service extensions.
             *
             * @return array<string, callable> An empty extension map.
             */
            public function getExtensions(): array
            {
                return [];
            }
        };

        return self::withApplication($application);
    }

    /**
     * Merges application definitions last to prevent silent required-reporter fallback.
     *
     * @param ServiceProviderInterface $application Application for the fixture.
     *
     * @return ContainerInterface The fixture result.
     */
    private static function withApplication(ServiceProviderInterface $application): ContainerInterface
    {
        return container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
    }

    /**
     * Supplies HTTP factories and records private reporting for middleware tests.
     *
     * @param list<\Throwable> $received Receives privately reported throwables.
     *
     * @return ContainerInterface The fixture result.
     */
    private static function httpServices(array &$received): ContainerInterface
    {
        $psr17 = new Psr17Factory();

        return self::services([
            ThrowableHandlerInterface::class => new CallableThrowableHandler(
                static function (\Throwable $error) use (&$received): void {
                    $received[] = $error;
                }
            ),
            ResponseFactoryInterface::class => $psr17,
            StreamFactoryInterface::class => $psr17,
        ]);
    }

    /**
     * Creates a synthetic request for the provider integration tests.
     *
     * @return ServerRequestInterface The fixture result.
     */
    private static function request(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/provider-test');
    }

    /**
     * Creates a downstream handler that throws the supplied failure.
     *
     * @param \Throwable $failure The synthetic failure to propagate.
     *
     * @return RequestHandlerInterface The fixture result.
     */
    private static function failsWith(\Throwable $failure): RequestHandlerInterface
    {
        return new class ($failure) implements RequestHandlerInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $failure The synthetic failure to propagate.
             */
            public function __construct(private \Throwable $failure)
            {
            }

            /**
             * Executes the synthetic downstream behavior for the requested test route.
             *
             * @param ServerRequestInterface $request The synthetic incoming request.
             *
             * @return ResponseInterface The fixture result.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };
    }

    /**
     * Snapshots native PHP callbacks while preserving their stacks and error masks.
     *
     * @return array{0: callable|null, 1: callable|null} Current error and exception callbacks.
     */
    private static function callbacks(): array
    {
        $error = set_error_handler(static fn (): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(static function (\Throwable $error): void {
        });
        restore_exception_handler();

        return [$error, $exception];
    }
}
