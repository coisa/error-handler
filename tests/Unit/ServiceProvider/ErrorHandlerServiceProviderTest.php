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
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ThrowableHandlerAggregateFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\NegotiatingThrowableResponseFactoryFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\WhoopsThrowableResponseFactoryFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\SymfonyCliThrowableHandlerFactory;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Whoops\Handler\PrettyPageHandler;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\InvokableFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Verifies lazy public definitions and their real FastForward factory behavior.
 *
 * @internal
 */
#[CoversClass(ErrorHandlerServiceProvider::class)]
#[UsesClass(ErrorHandlerFactory::class)]
#[UsesClass(ErrorHandlerMiddlewareFactory::class)]
#[UsesClass(ThrowableHandlerAggregateFactory::class)]
final class ErrorHandlerServiceProviderTest extends TestCase
{
    /** Verifies provider discovery exposes the supported IDs without installing callbacks. */
    public function testDiscoveryIsInertAndDefinesThePublicServices(): void
    {
        $before = self::callbacks();
        $provider = new ErrorHandlerServiceProvider();
        $definitions = $provider->getFactories();

        self::assertEqualsCanonicalizing([
            ErrorHandlerInterface::class,
            ErrorHandler::class,
            ErrorHandlerFactory::class,
            ThrowableHandlerInterface::class,
            ThrowableHandlerAggregate::class,
            SymfonyCliThrowableHandler::class,
            Serializer::class,
            SerializerInterface::class,
            ThrowableResponseFactoryInterface::class,
            NegotiatingThrowableResponseFactory::class,
            JsonThrowableResponseFactory::class,
            HtmlThrowableResponseFactory::class,
            WhoopsThrowableResponseFactory::class,
            PrettyPageHandler::class,
            ThrowableStreamFactoryInterface::class,
            ErrorHandlerMiddleware::class,
            ErrorHandlerMiddlewareFactory::class,
        ], array_keys($definitions));
        self::assertInstanceOf(ErrorHandlerFactory::class, $definitions[ErrorHandler::class]);
        self::assertInstanceOf(ThrowableHandlerAggregateFactory::class, $definitions[ThrowableHandlerAggregate::class]);
        self::assertInstanceOf(ErrorHandlerMiddlewareFactory::class, $definitions[ErrorHandlerMiddleware::class]);
        self::assertInstanceOf(
            NegotiatingThrowableResponseFactoryFactory::class,
            $definitions[NegotiatingThrowableResponseFactory::class],
        );
        self::assertInstanceOf(
            WhoopsThrowableResponseFactoryFactory::class,
            $definitions[WhoopsThrowableResponseFactory::class],
        );
        self::assertInstanceOf(
            SymfonyCliThrowableHandlerFactory::class,
            $definitions[SymfonyCliThrowableHandler::class],
        );
        self::assertSame([], $provider->getExtensions());
        self::assertSame($before, self::callbacks());
    }

    /**
     * Verifies each interface alias delegates to its concrete target and preserves identity.
     *
     * @param class-string $alias  the public interface service ID
     * @param class-string $target the concrete service ID resolved by the alias
     */
    #[DataProvider('provideAliasesResolveTheDeclaredTargetCases')]
    public function testAliasesResolveTheDeclaredTarget(string $alias, string $target): void
    {
        $service = new \stdClass();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with($target)->willReturn($service);
        $container->expects(self::never())->method('has');
        $definition = (new ErrorHandlerServiceProvider())->getFactories()[$alias];

        self::assertInstanceOf(AliasFactory::class, $definition);
        self::assertSame($service, $definition($container));
    }

    /** @return iterable<string, array{class-string, class-string}> Public alias target pairs. */
    public static function provideAliasesResolveTheDeclaredTargetCases(): iterable
    {
        yield 'process' => [ErrorHandlerInterface::class, ErrorHandler::class];

        yield 'reporter' => [ThrowableHandlerInterface::class, ThrowableHandlerAggregate::class];

        yield 'response' => [ThrowableResponseFactoryInterface::class, NegotiatingThrowableResponseFactory::class];

        yield 'serializer' => [SerializerInterface::class, Serializer::class];

        yield 'generic stream' => [ThrowableStreamFactoryInterface::class, GenericThrowableStreamFactory::class];
    }

    /**
     * Verifies reusable factory objects are constructed without resolving their dependencies.
     *
     * @param class-string $service the factory-object service ID
     */
    #[DataProvider('provideFactoryObjectsAreLazyInvokableDefinitionsCases')]
    public function testFactoryObjectsAreLazyInvokableDefinitions(string $service): void
    {
        $before = self::callbacks();
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::never())->method('get');
        $container->expects(self::never())->method('has');
        $definition = (new ErrorHandlerServiceProvider())->getFactories()[$service];

        self::assertInstanceOf(InvokableFactory::class, $definition);
        $first = $definition($container);
        $second = $definition($container);

        self::assertInstanceOf($service, $first);
        self::assertInstanceOf($service, $second);
        self::assertNotSame($first, $second);
        self::assertSame($before, self::callbacks());
    }

    /** @return iterable<string, array{class-string}> Factory objects supplied by the provider. */
    public static function provideFactoryObjectsAreLazyInvokableDefinitionsCases(): iterable
    {
        yield 'handler factory' => [ErrorHandlerFactory::class];

        yield 'middleware factory' => [ErrorHandlerMiddlewareFactory::class];
    }

    /**
     * Reads process-global callbacks while restoring both temporary probe stacks.
     *
     * @return array{null|callable, null|callable} current error and exception callbacks
     *
     * @phpstan-impure
     */
    private static function callbacks(): array
    {
        /** @return bool A temporary callback used only to inspect the active error callback. */
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /** @param \Throwable $error The unused exception-probe input. */
            static function (\Throwable $error): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
