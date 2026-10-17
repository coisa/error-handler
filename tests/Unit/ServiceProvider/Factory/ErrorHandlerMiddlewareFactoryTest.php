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

namespace CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\PhpErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\PhpLastErrorShutdownHandler;
use CoiSA\ErrorHandler\Handler\ShutdownHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\ThrowErrorExceptionPhpErrorHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies factory-created middleware owns independent native scopes and explicit responses.
 *
 * @internal
 */
#[CoversClass(ErrorHandlerMiddlewareFactory::class)]
#[UsesClass(ErrorHandlerFactory::class)]
#[UsesClass(ErrorHandler::class)]
#[UsesClass(ErrorHandlerMiddleware::class)]
#[UsesClass(ThrowErrorExceptionPhpErrorHandler::class)]
#[UsesClass(PhpLastErrorShutdownHandler::class)]
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ErrorHandlerMiddlewareFactoryTest extends TestCase
{
    /** Verifies nested fresh handlers report once, preserve response identity and restore outer scopes. */
    public function testFactoryCreatesIndependentNestedMiddlewareScopes(): void
    {
        $original = new \RuntimeException('nested downstream failure');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $expected = self::createStub(ResponseInterface::class);
        $responses = $this->createMock(ThrowableResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($original))->willReturn($expected)
        ;
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(6))->method('get')->willReturnMap([
            [ErrorHandlerFactory::class, new ErrorHandlerFactory()],
            [ThrowableResponseFactoryInterface::class, $responses],
            [ThrowableHandlerInterface::class, $reporter],
        ]);
        $container->expects(self::exactly(4))->method('has')->willReturnMap([
            [PhpErrorHandlerInterface::class, false],
            [ShutdownHandlerInterface::class, false],
        ]);
        $before = self::callbacks();
        $factory = new ErrorHandlerMiddlewareFactory();
        $outer = $factory($container);
        $inner = $factory($container);

        self::assertNotSame($outer, $inner);
        self::assertSame($before, self::callbacks());
        $request = self::createStub(ServerRequestInterface::class);
        $failing = $this->createMock(RequestHandlerInterface::class);
        $failing->expects(self::once())->method('handle')->with(self::identicalTo($request))
            ->willThrowException($original)
        ;
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturnCallback(
            /**
             * Enters the inner middleware while requiring the outer callbacks to survive it.
             *
             * @param ServerRequestInterface $request the original request delegated by the outer scope
             *
             * @return ResponseInterface the exact response returned by the inner renderer
             */
            static function (ServerRequestInterface $request) use ($inner, $failing): ResponseInterface {
                $outerCallbacks = self::callbacks();
                $response = $inner->process($request, $failing);
                self::assertSame($outerCallbacks, self::callbacks());

                return $response;
            },
        );

        self::assertSame($expected, $outer->process($request, $next));
        self::assertSame($before, self::callbacks());
    }

    /** Verifies response resolution failure remains identical and precedes handler construction. */
    public function testResponseConstructionFailureDoesNotCreateOrRegisterAHandler(): void
    {
        $failure = new \LogicException('response factory unavailable');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(2))->method('get')->willReturnCallback(
            /**
             * Supplies the reusable factory but fails the required response dependency.
             *
             * @param string $service the requested dependency service ID
             *
             * @return ErrorHandlerFactory the inert reusable factory object
             *
             * @throws \LogicException the configured response-resolution failure
             */
            static function (string $service) use ($failure): ErrorHandlerFactory {
                if (ThrowableResponseFactoryInterface::class === $service) {
                    throw $failure;
                }
                self::assertSame(ErrorHandlerFactory::class, $service);

                return new ErrorHandlerFactory();
            },
        );
        $container->expects(self::never())->method('has');
        $before = self::callbacks();

        try {
            (new ErrorHandlerMiddlewareFactory())($container);
            self::fail('A required response failure must prevent middleware construction.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame($before, self::callbacks());
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
