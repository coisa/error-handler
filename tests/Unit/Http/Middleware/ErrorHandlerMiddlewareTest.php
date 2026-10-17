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

namespace CoiSA\ErrorHandler\Test\Unit\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies direct PSR middleware behavior, including the former loopback response tuples.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware
 */
final class ErrorHandlerMiddlewareTest extends TestCase
{
    /** Verifies success returns the exact downstream response with one lifecycle scope. */
    public function testSuccessfulResponseUsesOneScopeWithoutRendering(): void
    {
        $messages = new Psr17Factory();
        $request = $messages->createServerRequest('GET', 'http://localhost');
        $response = $messages->createResponse(204);
        $scope = $this->createMock(ErrorHandlerInterface::class);
        $scope->expects(self::once())->method('register');
        $scope->expects(self::once())->method('unregister');
        $factory = $this->createMock(ThrowableResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponseFromThrowable');
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->with(self::identicalTo($request))->willReturn($response);
        self::assertSame($response, (new ErrorHandlerMiddleware($scope, $factory))->process($request, $next));
    }

    /** Verifies rendering receives the original event and finally restores the scope on failure. */
    public function testRenderingFailurePropagatesAfterUnregistering(): void
    {
        $original = new \Error('downstream');
        $primary = new \RuntimeException('response factory');
        $scope = $this->createMock(ErrorHandlerInterface::class);
        $scope->expects(self::once())->method('register');
        $scope->expects(self::once())->method('unregister');
        $factory = $this->createMock(ThrowableResponseFactoryInterface::class);
        $factory->expects(self::once())->method('createResponseFromThrowable')->with(self::identicalTo($original))
            ->willThrowException($primary)
        ;
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($original);
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://localhost');

        try {
            (new ErrorHandlerMiddleware($scope, $factory))->process($request, $next);
            self::fail('The response factory failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);
        }
    }

    /** Verifies registration failure stops before entering a scope or calling downstream code. */
    public function testRegistrationFailureDoesNotEnterTheRequestScope(): void
    {
        $primary = new \RuntimeException('registration');
        $scope = $this->createMock(ErrorHandlerInterface::class);
        $scope->expects(self::once())->method('register')->willThrowException($primary);
        $scope->expects(self::never())->method('unregister');
        $factory = $this->createMock(ThrowableResponseFactoryInterface::class);
        $factory->expects(self::never())->method('createResponseFromThrowable');
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::never())->method('handle');

        try {
            (new ErrorHandlerMiddleware($scope, $factory))
                ->process((new Psr17Factory())->createServerRequest('GET', 'http://localhost'), $next)
            ;
            self::fail('Registration must fail before downstream execution.');
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);
        }
    }

    /**
     * Verifies exact legacy statuses/bodies, one report and native callback restoration without a server.
     *
     * @param class-string<\RuntimeException|\TypeError> $class   the synthetic event type
     * @param string                                     $message the synthetic downstream message
     * @param int                                        $code    the synthetic downstream code
     * @param int                                        $status  the existing 2.x status policy result
     *
     * @dataProvider provideLegacyResponsesAndRealCallbackRestorationCases
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testLegacyResponsesAndRealCallbackRestoration(
        string $class,
        string $message,
        int $code,
        int $status
    ): void {
        $original = new $class($message, $code);
        $reports = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Records original identity and emits the same body as the old loopback fixture.
             *
             * @param \Throwable $event the original event reported during capture
             */
            static function (\Throwable $event) use (&$reports): void {
                $reports[] = $event;
                echo 'legacy captured: ' . $event->getMessage();
            },
        );
        $messages = new Psr17Factory();
        $middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), new ThrowableResponseFactory(
            new ThrowableStreamFactory($messages, $reporter),
            $messages,
        ));
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->method('handle')->willThrowException($original);

        /** @return bool confirms the previous callback owns this synthetic PHP error */
        $previousError = static fn(): bool => true;

        /**
         * Receives a previous exception only if callback restoration is incorrect.
         *
         * @param \Throwable $event the previous callback's input
         *
         * @throws \LogicException if an unexpected native exception reaches the sentinel
         */
        $previousException = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected previous exception callback.', 0, $event);
        };
        set_error_handler($previousError);
        set_exception_handler($previousException);

        try {
            $response = $middleware->process($messages->createServerRequest('GET', 'http://localhost'), $next);
            self::assertSame($status, $response->getStatusCode());
            self::assertSame('legacy captured: ' . $original->getMessage(), (string) $response->getBody());
            self::assertSame([$original], $reports);
            self::assertSame($previousError, set_error_handler($previousError));
            restore_error_handler();
            self::assertSame($previousException, set_exception_handler($previousException));
            restore_exception_handler();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * Supplies scalar descriptions so throwable traces never cross PHPUnit's process protocol.
     *
     * @return array<string, array{class-string<\RuntimeException|\TypeError>, string, int, int}> legacy response cases
     */
    public static function provideLegacyResponsesAndRealCallbackRestorationCases(): iterable
    {
        return [
            'error' => [\RuntimeException::class, 'synthetic downstream failure', 0, 500],
            'unavailable' => [\RuntimeException::class, 'synthetic upstream address', 503, 500],
            'client-error' => [\RuntimeException::class, 'synthetic client failure', 404, 404],
            'type-error' => [\TypeError::class, 'synthetic type mismatch', 0, 500],
        ];
    }

    /**
     * Verifies opt-in JSON reports once and restores native callbacks on every result.
     *
     * @param string $failureStage the optional reporter or HTTP factory failure
     *
     * @dataProvider provideJsonCompositionRestoresNativeCallbacksCases
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testJsonCompositionRestoresNativeCallbacks(string $failureStage): void
    {
        $this->expectOutputString('');
        $original = new \RuntimeException('Private downstream diagnostic', 503);
        $failure = new \RuntimeException('Configured collaborator failure');
        $reports = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Records the original privately and optionally fails before rendering.
             *
             * @param \Throwable $event the diagnostic event delivered by the decorator
             *
             * @throws \RuntimeException the identical configured reporter failure
             */
            static function (\Throwable $event) use (&$reports, $failureStage, $failure): void {
                $reports[] = $event;

                if ('reporter' === $failureStage) {
                    throw $failure;
                }
            },
        );
        $messages = new Psr17Factory();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        if ('reporter' === $failureStage) {
            $responses->expects(self::never())->method('createResponse');
        } elseif ('renderer' === $failureStage) {
            $responses->expects(self::once())->method('createResponse')->willThrowException($failure);
        } else {
            $responses->expects(self::once())->method('createResponse')->with(503)
                ->willReturn($messages->createResponse(503))
            ;
        }
        $middleware = new ErrorHandlerMiddleware(
            new ErrorHandler($reporter),
            new ReportingThrowableResponseFactory($reporter, new JsonThrowableResponseFactory($responses, $messages)),
        );
        $next = $this->createMock(RequestHandlerInterface::class);
        $next->expects(self::once())->method('handle')->willThrowException($original);

        /** @return bool whether the surrounding callback owns a synthetic warning */
        $previousError = static fn(): bool => true;

        /**
         * Retains the surrounding exception callback without accepting an uncaught fixture event.
         *
         * @param \Throwable $event an unexpected exception escaping the middleware test
         *
         * @throws \LogicException if a fixture bypasses the expected local catch
         */
        $previousException = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected previous exception callback.', 0, $event);
        };

        set_error_handler($previousError);
        set_exception_handler($previousException);
        $bufferLevel = ob_get_level();
        $response = null;
        $caught = null;

        try {
            try {
                $response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);
            } catch (\Throwable $actual) {
                $caught = $actual;
            }

            $actualError = set_error_handler($previousError);
            restore_error_handler();
            $actualException = set_exception_handler($previousException);
            restore_exception_handler();

            self::assertSame($previousError, $actualError);
            self::assertSame($previousException, $actualException);
            self::assertSame($bufferLevel, ob_get_level());
            self::assertSame([$original], $reports);

            if ('none' === $failureStage) {
                self::assertNull($caught);
                self::assertInstanceOf(ResponseInterface::class, $response);
                self::assertSame(503, $response->getStatusCode());
                self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
            } else {
                self::assertNull($response);
                self::assertSame($failure, $caught);
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * Supplies independent successful, reporter-failure and renderer-failure paths.
     *
     * @return array<string, array{string}> scalar scenarios for native PHPUnit isolation
     */
    public static function provideJsonCompositionRestoresNativeCallbacksCases(): iterable
    {
        return [
            'JSON response' => ['none'],
            'reporter failure' => ['reporter'],
            'renderer failure' => ['renderer'],
        ];
    }
}
