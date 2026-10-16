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

namespace CoiSA\ErrorHandler\Test\Functional;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Covers independent direct deliveries and reporter failure within a middleware scope.
 *
 * @internal
 *
 * @coversNothing
 */
final class ErrorHandlerLifecycleTest extends TestCase
{
    /**
     * Verifies a failed direct delivery does not poison later independent calls.
     */
    public function testDirectDeliveryCanResumeAfterReporterFailure(): void
    {
        $calls = 0;
        $failure = new \RuntimeException('Temporary reporter failure');
        $handler = new ErrorHandler(new CallableThrowableHandler(
            static function () use (&$calls, $failure): void {
                if (1 === ++$calls) {
                    throw $failure;
                }
            },
        ));

        try {
            $handler->handleThrowable(new \RuntimeException('First delivery'));
            self::fail('The reporter failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
        $handler->handleThrowable(new \RuntimeException('Independent delivery'));
        self::assertSame(2, $calls);
    }

    /**
     * Verifies unregistering ends suppression even without a new registration.
     */
    public function testDirectDeliveryAfterFailedRegistrationIsIndependent(): void
    {
        $calls = 0;
        $handler = new ErrorHandler(new CallableThrowableHandler(static function () use (&$calls): void {
            if (1 === ++$calls) {
                throw new \RuntimeException('Reporter failed');
            }
        }));
        $handler->register();

        try {
            try {
                $handler->handleThrowable(new \RuntimeException('First delivery'));
            } catch (\RuntimeException) {
            }
            $second = new \RuntimeException('Suppressed retry');

            try {
                $handler->handleThrowable($second);
                self::fail('The failed registration must suppress retries.');
            } catch (\RuntimeException $caught) {
                self::assertSame($second, $caught);
            }
            self::assertSame(1, $calls);
        } finally {
            $handler->unregister();
        }
        $handler->handleThrowable(new \RuntimeException('Independent delivery'));
        self::assertSame(2, $calls);
    }

    /**
     * Verifies middleware propagates a failed reporter without rendering through it again.
     */
    #[WithoutErrorHandler]
    public function testMiddlewareDoesNotRetryReporterAfterPhpWarning(): void
    {
        $previousError = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $previousException = set_exception_handler(static function (): void {});
        restore_exception_handler();
        $calls = 0;
        $failure = new \RuntimeException('Reporter failed');
        $reporter = new CallableThrowableHandler(static function () use (&$calls, $failure): void {
            ++$calls;

            throw $failure;
        });
        $psr17 = new Psr17Factory();
        $errors = new ErrorHandler($reporter);
        $middleware = new ErrorHandlerMiddleware(
            $errors,
            new ThrowableResponseFactory(new ThrowableStreamFactory($psr17, $reporter), $psr17),
        );
        $downstream = new class implements RequestHandlerInterface {
            /**
             * Triggers a reportable warning inside the active middleware scope.
             *
             * @param ServerRequestInterface $request the synthetic request
             *
             * @return ResponseInterface a response that must never be reached
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                trigger_error('Downstream warning', E_USER_WARNING);

                throw new \LogicException('The warning reporter should have failed.');
            }
        };
        $previousReporting = error_reporting(E_ALL);

        try {
            $middleware->process($psr17->createServerRequest('GET', 'https://example.test'), $downstream);
            self::fail('Reporter failure must propagate to the application.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        } finally {
            error_reporting($previousReporting);
        }
        self::assertSame(1, $calls);
        $activeError = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $activeException = set_exception_handler(static function (): void {});
        restore_exception_handler();
        self::assertSame($previousError, $activeError);
        self::assertSame($previousException, $activeException);
    }
}
