<?php

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Functional\Http\Middleware;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class ErrorHandlerCleanupTest extends TestCase
{
    public function testRestoresHandlersEvenWhenTheResponseFactoryThrows(): void
    {
        $previousError = static fn (): bool => true;
        $previousException = static function (\Throwable $error): void {
        };
        set_error_handler($previousError, E_USER_NOTICE);
        set_exception_handler($previousException);
        $failure = new \RuntimeException('response factory failed');
        $responses = new class ($failure) implements ThrowableResponseFactoryInterface {
            public function __construct(private \Throwable $failure)
            {
            }
            public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
            {
                throw $this->failure;
            }
        };
        $next = new class () implements RequestHandlerInterface {
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
            $activeError = set_error_handler(static fn (): bool => true);
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
