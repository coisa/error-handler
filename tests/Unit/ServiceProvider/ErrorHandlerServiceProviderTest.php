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
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactoryInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use FastForward\Container\Factory\AliasFactory;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Container\container;

/**
 * Exercises optional lifecycle composition through the real FastForward container.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider
 * @covers \CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory
 */
final class ErrorHandlerServiceProviderTest extends TestCase
{
    /**
     * Resolving cached process, middleware and factory services leaves native callbacks alone.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testDiscoveryAndCachedAliasesDoNotInstallCallbacks(): void
    {
        /** @return bool retains ownership of synthetic surrounding PHP errors */
        $previousError = static fn(): bool => true;

        /**
         * Rejects an unexpected native exception callback invocation.
         *
         * @param \Throwable $event an event that should remain in the calling test
         */
        $previousException = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected callback delivery.', 0, $event);
        };
        set_error_handler($previousError);
        set_exception_handler($previousException);

        try {
            $reporter = $this->createMock(ThrowableHandlerInterface::class);
            $reporter->expects(self::never())->method('handleThrowable');
            $services = container(ErrorHandlerServiceProvider::class, $this->application($reporter));
            $process = $services->get(ErrorHandler::class);
            self::assertInstanceOf(ErrorHandler::class, $process);
            self::assertSame($process, $services->get(ErrorHandlerInterface::class));
            self::assertSame($process, $services->get(ErrorHandler::class));
            $middleware = $services->get(ErrorHandlerMiddleware::class);
            self::assertInstanceOf(ErrorHandlerMiddleware::class, $middleware);
            self::assertSame($middleware, $services->get(ErrorHandlerMiddleware::class));
            $factory = $services->get(ErrorHandlerMiddlewareFactory::class);
            self::assertInstanceOf(ErrorHandlerMiddlewareFactory::class, $factory);
            self::assertSame($factory, $services->get(ErrorHandlerMiddlewareFactory::class));
            self::assertNotSame($middleware, $factory($services));
            self::assertSame($previousError, set_error_handler($previousError));
            restore_error_handler();
            self::assertSame($previousException, set_exception_handler($previousException));
            restore_exception_handler();
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /** The base retains captured reporter output and the existing 2.x status policy. */
    public function testBaseRetainsLegacyCaptureDefaults(): void
    {
        $this->expectOutputString('');
        $original = new \RuntimeException('Legacy reason', 503);
        $reports = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Emits an application-selected body for the legacy capture adapter.
             *
             * @param \Throwable $event the original diagnostic event
             */
            static function (\Throwable $event) use (&$reports): void {
                $reports[] = $event;
                echo 'Legacy captured output';
            },
        );
        $services = container(ErrorHandlerServiceProvider::class, $this->application($reporter));

        /** @var ThrowableResponseFactoryInterface $renderer */
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        $response = $renderer->createResponseFromThrowable($original);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Legacy reason', $response->getReasonPhrase());
        self::assertSame('Legacy captured output', (string) $response->getBody());
        self::assertSame([$original], $reports);
        self::assertSame(
            $services->get(ThrowableStreamFactory::class),
            $services->get(ThrowableStreamFactoryInterface::class),
        );
    }

    /** A missing application reporter is a construction failure, never an empty aggregate. */
    public function testApplicationMustProvideItsReporter(): void
    {
        $services = container(ErrorHandlerServiceProvider::class);

        $this->expectException(ContainerExceptionInterface::class);
        $services->get(ErrorHandlerInterface::class);
    }

    /**
     * Native callback identities prove distinct HTTP handlers around a cached process handler.
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testFactoryCreatesIndependentNestedScopes(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $services = container(ErrorHandlerServiceProvider::class, $this->application($reporter));

        /** @var ErrorHandler $process */
        $process = $services->get(ErrorHandler::class);

        /** @var ErrorHandlerMiddlewareFactory $factory */
        $factory = $services->get(ErrorHandlerMiddlewareFactory::class);
        $outer = $factory($services);
        $inner = $factory($services);
        $response = (new Psr17Factory())->createResponse(204);
        $scopes = [];
        $innerNext = $this->createMock(RequestHandlerInterface::class);
        $innerNext->expects(self::once())->method('handle')->willReturnCallback(
            /**
             * Observes the nested public callback and returns the exact downstream response.
             *
             * @return ResponseInterface the unmodified successful result
             */
            static function () use (&$scopes, $response): ResponseInterface {
                $scopes[] = self::currentErrorHandler();

                return $response;
            },
        );
        $outerNext = $this->createMock(RequestHandlerInterface::class);
        $outerNext->expects(self::once())->method('handle')->willReturnCallback(
            /**
             * Enters another middleware scope and verifies restoration of its caller.
             *
             * @param ServerRequestInterface $request the unchanged outer request
             *
             * @return ResponseInterface the nested result
             */
            static function (ServerRequestInterface $request) use (&$scopes, $inner, $innerNext): ResponseInterface {
                $outerHandler = self::currentErrorHandler();
                $scopes[] = $outerHandler;
                $actual = $inner->process($request, $innerNext);
                self::assertSame($outerHandler, self::currentErrorHandler());

                return $actual;
            },
        );

        $process->register();

        try {
            self::assertSame($process, self::currentErrorHandler());
            $actual = $outer->process((new Psr17Factory())->createServerRequest('GET', '/nested'), $outerNext);
            self::assertSame($response, $actual);
            self::assertCount(2, $scopes);
            self::assertNotSame($process, $scopes[0]);
            self::assertNotSame($process, $scopes[1]);
            self::assertNotSame($scopes[0], $scopes[1]);
            self::assertSame($process, self::currentErrorHandler());
        } finally {
            $process->unregister();
        }
    }

    /**
     * Supplies only application-owned interfaces, leaving scope construction to the provider.
     *
     * @param ThrowableHandlerInterface $reporter the private diagnostic destination
     *
     * @return ArrayServiceProvider required external collaborators
     */
    private function application(ThrowableHandlerInterface $reporter): ArrayServiceProvider
    {
        return new ArrayServiceProvider([
            ResponseFactoryInterface::class => new ServiceFactory(new Psr17Factory()),
            StreamFactoryInterface::class => AliasFactory::get(ResponseFactoryInterface::class),
            ThrowableHandlerInterface::class => new ServiceFactory($reporter),
        ]);
    }

    /**
     * Reads the installed PHP error callback without retaining the temporary probe.
     *
     * @return ErrorHandler the object publicly installed by the active scope
     */
    private static function currentErrorHandler(): ErrorHandler
    {
        /** @return bool keeps the native probe inert */
        $probe = static fn(): bool => true;
        $callback = set_error_handler($probe);
        restore_error_handler();
        self::assertIsArray($callback);
        self::assertInstanceOf(ErrorHandler::class, $callback[0]);

        return $callback[0];
    }
}
