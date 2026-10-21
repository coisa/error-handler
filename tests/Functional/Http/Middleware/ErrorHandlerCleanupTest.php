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

namespace CoiSA\ErrorHandler\Test\Functional\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Verifies native callback restoration after response construction fails.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Http\Middleware
 *
 * @internal
 */
#[CoversNothing]
final class ErrorHandlerCleanupTest extends TestCase
{
    /**
     * Verifies response failures preserve the previous error and exception callbacks.
     */
    public function testRestoresHandlersEvenWhenTheResponseFactoryThrows(): void
    {
        $previousError = static fn(): bool => true;
        $previousException = static function (\Throwable $error): void {
        };
        set_error_handler($previousError, E_USER_NOTICE);
        set_exception_handler($previousException);
        $failure = new \RuntimeException('response factory failed');
        $responses = new class ($failure) implements ThrowableResponseFactoryInterface {
            /**
             * Stores synthetic collaborators or failures for this test fixture.
             *
             * @param \Throwable $failure the synthetic failure to propagate
             */
            public function __construct(private \Throwable $failure)
            {
            }

            /**
             * Throws the configured rendering failure to exercise callback restoration.
             *
             * @param \Throwable             $throwable the throwable being rendered
             * @param ServerRequestInterface $request   the incoming request associated with the failure
             *
             * @return ResponseInterface the fixture result
             */
            public function createResponseFromThrowable(
                \Throwable $throwable,
                ServerRequestInterface $request,
            ): ResponseInterface {
                throw $this->failure;
            }
        };
        $next = new class implements RequestHandlerInterface {
            /**
             * Executes the synthetic downstream behavior for the requested test route.
             *
             * @param ServerRequestInterface $request the synthetic incoming request
             *
             * @return ResponseInterface the fixture result
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new \TypeError('request failed');
            }
        };
        $reporter = new CallableThrowableHandler(static function (): void {
        });
        $middleware = new ErrorHandlerMiddleware(new ErrorHandler($reporter), $responses);

        try {
            $middleware->process((new Psr17Factory())->createServerRequest('GET', '/'), $next);
            self::fail('Response factory failure must propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($failure, $actual);
            $activeError = set_error_handler(static fn(): bool => true);
            restore_error_handler();
            $activeException = set_exception_handler(static function (\Throwable $error): void {
            });
            restore_exception_handler();
            self::assertSame($previousError, $activeError);
            self::assertSame($previousException, $activeException);
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }
}
