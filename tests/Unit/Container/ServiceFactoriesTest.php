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

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\Factory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchErrorEventThrowableHandler;
use CoiSA\ErrorHandler\Handler\DispatchThrowableHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use CoiSA\ErrorHandler\EventDispatcher\Event\ErrorEvent;

/**
 * Verifies injected collaborators are used through public factory results.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Container\Factory\AliasFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\DispatchErrorEventThrowableHandlerFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\DispatchThrowableHandlerFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\ErrorHandlerFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\ErrorHandlerMiddlewareFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\ThrowableHandlerAggregateFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\ThrowableResponseFactoryFactory
 * @covers \CoiSA\ErrorHandler\Container\Factory\ThrowableStreamFactoryFactory
 */
final class ServiceFactoriesTest extends TestCase
{
    /**
     * Verifies alias delegation preserves every PSR-11 value, including null and scalars.
     *
     * @param mixed $value the exact parent-owned value
     *
     * @dataProvider provideAliasReturnsTheExactValueCases
     */
    public function testAliasReturnsTheExactValue($value): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::once())->method('get')->with('fixture.alias')->willReturn($value);
        self::assertSame($value, (new Factory\AliasFactory('fixture.alias'))($container));
    }

    /**
     * Supplies identity-sensitive and false-like values accepted by PSR-11.
     *
     * @return array<string, array{mixed}> representative legal PSR-11 values
     */
    public static function provideAliasReturnsTheExactValueCases(): iterable
    {
        return ['null' => [null], 'false' => [false], 'zero' => [0], 'text' => ['value'],
            'array' => [['key' => 'value']], 'object' => [new \stdClass()]];
    }

    /** Verifies the alias does not wrap or replace a container failure. */
    public function testAliasPreservesContainerFailure(): void
    {
        $failure = new \RuntimeException('lookup');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException($failure);

        try {
            (new Factory\AliasFactory('fixture.alias'))($container);
            self::fail('Lookup failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Verifies both dispatch factories inject the selected dispatcher without discovery. */
    public function testDispatchFactoriesUseTheExactDispatcher(): void
    {
        $original = new \Error('incoming');
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $events = [];
        $dispatcher->expects(self::exactly(2))->method('dispatch')->willReturnCallback(
            /**
             * Records the exact object supplied by each constructed reporter.
             *
             * @param object $event the dispatched object
             *
             * @return object the same dispatched object
             */
            static function (object $event) use (&$events): object {
                $events[] = $event;

                return $event;
            },
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->with(EventDispatcherInterface::class)->willReturn($dispatcher);
        (new Factory\DispatchThrowableHandlerFactory())($container)->handleThrowable($original);
        (new Factory\DispatchErrorEventThrowableHandlerFactory())($container)->handleThrowable($original);
        self::assertSame($original, $events[0]);
        self::assertInstanceOf(ErrorEvent::class, $events[1]);
        self::assertSame($original, $events[1]->getThrowable());
    }

    /** Verifies optional handlers are selected and constructor resolution installs no callbacks. */
    public function testErrorHandlerFactoryUsesExplicitOptionalHandlers(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $php = $this->createMock(PhpErrorHandlerInterface::class);
        $php->expects(self::once())->method('handlePhpError')->with(E_NOTICE, 'event', '/fixture.php', 8);
        $shutdown = $this->createMock(ShutdownHandlerInterface::class);
        $shutdown->expects(self::never())->method('handleShutdown');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            [ThrowableHandlerInterface::class, $reporter], [PhpErrorHandlerInterface::class, $php],
            [ShutdownHandlerInterface::class, $shutdown],
        ]);
        $handler = (new Factory\ErrorHandlerFactory())($container);
        $handler->handlePhpError(E_NOTICE, 'event', '/fixture.php', 8);
        $handler->handleShutdown();
    }

    /** Verifies absent optional adapters preserve the established PHP-error conversion default. */
    public function testErrorHandlerFactoryKeepsDefaultAdapters(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::callback(
            /**
             * Checks metadata from the real default PHP-error adapter.
             *
             * @param \Throwable $event the converted warning
             *
             * @return bool whether the adapter retained the supplied metadata
             */
            static fn(\Throwable $event): bool => $event instanceof \ErrorException
                && E_USER_WARNING === $event->getSeverity() && 'fixture warning' === $event->getMessage(),
        ));
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->with(ThrowableHandlerInterface::class)->willReturn($reporter);
        $mask = error_reporting(E_ALL);

        try {
            $handler = (new Factory\ErrorHandlerFactory())($container);
            $handler->handlePhpError(E_USER_WARNING, 'fixture warning', __FILE__, 4);
        } finally {
            error_reporting($mask);
        }
    }

    /** Verifies configured aggregation orders optional handlers and preserves the original event. */
    public function testAggregateFactoryComposesAvailableHandlersInOrder(): void
    {
        $original = new \RuntimeException('original');
        $seen = [];
        $callable = new CallableThrowableHandler(
            /**
             * Records the first explicitly configured reporter.
             *
             * @param \Throwable $event the original event
             */
            static function (\Throwable $event) use (&$seen): void {
                $seen[] = ['callable', $event];
            },
        );
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')->willReturnCallback(
            /**
             * Records raw and wrapped dispatch in the configured order.
             *
             * @param object $event the dispatched object
             *
             * @return object the same dispatched object
             */
            static function (object $event) use (&$seen): object {
                $seen[] = ['dispatch', $event];

                return $event;
            },
        );
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturnMap([
            [CallableThrowableHandler::class, $callable],
            [DispatchThrowableHandler::class, new DispatchThrowableHandler($dispatcher)],
            [DispatchErrorEventThrowableHandler::class, new DispatchErrorEventThrowableHandler($dispatcher)],
        ]);
        (new Factory\ThrowableHandlerAggregateFactory())($container)->handleThrowable($original);
        self::assertSame(['callable', 'dispatch', 'dispatch'], array_column($seen, 0));
        self::assertSame($original, $seen[0][1]);
        self::assertSame($original, $seen[1][1]);
        self::assertInstanceOf(ErrorEvent::class, $seen[2][1]);
        self::assertSame($original, $seen[2][1]->getThrowable());
    }

    /** Verifies unavailable handlers are never resolved and an empty aggregate returns normally. */
    public function testAggregateFactoryDoesNotResolveMissingHandlers(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('has')->willReturn(false);
        $container->expects(self::never())->method('get');
        (new Factory\ThrowableHandlerAggregateFactory())($container)->handleThrowable(new \Error('ignored'));
    }

    /** Verifies stream and response factory results use the supplied PSR collaborators. */
    public function testMessageFactoriesDelegateOriginalFailureAndBody(): void
    {
        $messages = new Psr17Factory();
        $original = new \RuntimeException('incoming', 404);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original))
            ->willReturnCallback(
                /**
                 * Emits the public fixture body.
                 *
                 * @param \Throwable $event the supplied reporting event
                 */
                static function (\Throwable $event): void {
                    echo 'captured';
                },
            )
        ;
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [StreamFactoryInterface::class, $messages], [ThrowableHandlerInterface::class, $reporter],
        ]);
        $streams = (new Factory\ThrowableStreamFactoryFactory())($container);
        self::assertSame('captured', (string) $streams->createStreamFromThrowable($original));
        $body = $messages->createStream('delegated');
        $throwableStreams = $this->createMock(ThrowableStreamFactoryInterface::class);
        $throwableStreams->expects(self::once())->method('createStreamFromThrowable')
            ->with(self::identicalTo($original))->willReturn($body)
        ;
        $responses = $this->createMock(ContainerInterface::class);
        $responses->method('get')->willReturnMap([
            [ThrowableStreamFactoryInterface::class, $throwableStreams], [ResponseFactoryInterface::class, $messages],
        ]);
        $response = (new Factory\ThrowableResponseFactoryFactory())($responses)->createResponseFromThrowable($original);
        self::assertSame($body, $response->getBody());
        self::assertSame(404, $response->getStatusCode());
    }

    /** Verifies the middleware factory returns a usable scope around the supplied request handler. */
    public function testMiddlewareFactoryReturnsTheDownstreamResponse(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $responses = $this->createMock(ThrowableResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponseFromThrowable');
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willReturnMap([
            [ErrorHandler::class, new ErrorHandler($reporter)], [ThrowableResponseFactoryInterface::class, $responses],
        ]);
        $messages = new Psr17Factory();
        $request = $messages->createServerRequest('GET', 'http://localhost');
        $response = $messages->createResponse(204);
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);
        $middleware = (new Factory\ErrorHandlerMiddlewareFactory())($container);
        self::assertSame($response, $middleware->process($request, $next));
    }
}
