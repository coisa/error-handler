<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Functional\ServiceProvider;

use CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;
use CoiSA\ErrorHandler\Exception\ErrorException;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
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

final class ErrorHandlerServiceProviderTest extends TestCase
{
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

    #[DataProvider('requiredReporterFailures')]
    public function testRequiredReporterFailureCannotFallBackToASilentDefault(
        string $service,
        \Throwable $failure,
        string $expectedException
    ): void {
        $application = new class ($failure) implements ServiceProviderInterface {
            public function __construct(private \Throwable $failure)
            {
            }

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

            public function getExtensions(): array
            {
                return [];
            }
        };
        $container = self::withApplication($application);
        $this->expectException($expectedException);

        $container->get($service);
    }

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

    public function testOptionalPsr14DispatcherReceivesTheThrowableAndErrorEvent(): void
    {
        $dispatcher = new class () implements EventDispatcherInterface {
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };
        $container = self::services([EventDispatcherInterface::class => $dispatcher]);
        $error = new \RuntimeException('Dispatched');

        $container->get(ErrorHandlerInterface::class)->handleThrowable($error);

        self::assertCount(2, $dispatcher->events);
        self::assertSame($error, $dispatcher->events[0]);
        self::assertInstanceOf(ErrorEvent::class, $dispatcher->events[1]);
        self::assertSame($error, $dispatcher->events[1]->getThrowable());
    }

    #[WithoutErrorHandler]
    public function testCustomPhpAndShutdownHandlersAreInjected(): void
    {
        $phpHandler = new class () implements PhpErrorHandlerInterface {
            public array $messages = [];

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
            public function __construct(
                private ErrorHandlerMiddleware $inner,
                private RequestHandlerInterface $downstream
            ) {
            }

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

    #[WithoutErrorHandler]
    public function testRendererFailureRestoresTheProcessHandler(): void
    {
        $failure = new \RuntimeException('Renderer failed');
        $renderer = new class ($failure) implements ThrowableResponseFactoryInterface {
            public function __construct(private \Throwable $failure)
            {
            }

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

    /** @param array<class-string, object> $services */
    private static function services(array $services = []): ContainerInterface
    {
        $application = new class ($services) implements ServiceProviderInterface {
            public function __construct(private array $services)
            {
            }

            public function getFactories(): array
            {
                return array_map(static fn (object $service): \Closure =>
                    static fn (): object => $service, $this->services);
            }

            public function getExtensions(): array
            {
                return [];
            }
        };

        return self::withApplication($application);
    }

    private static function withApplication(ServiceProviderInterface $application): ContainerInterface
    {
        return container(new AggregateServiceProvider(new ErrorHandlerServiceProvider(), $application));
    }

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

    private static function request(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/provider-test');
    }

    private static function failsWith(\Throwable $failure): RequestHandlerInterface
    {
        return new class ($failure) implements RequestHandlerInterface {
            public function __construct(private \Throwable $failure)
            {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw $this->failure;
            }
        };
    }

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
