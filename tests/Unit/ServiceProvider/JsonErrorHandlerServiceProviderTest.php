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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function FastForward\Config\config;
use function FastForward\Container\container;

/**
 * Verifies the opt-in JSON profile with real Config, provider precedence and native scopes.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory
 * @covers \CoiSA\ErrorHandler\ServiceProvider\JsonErrorHandlerServiceProvider
 */
final class JsonErrorHandlerServiceProviderTest extends TestCase
{
    /** The class-string initializer selects public JSON and one private report by default. */
    public function testDefaultProfileReportsOnceAndKeepsTheConcreteRendererPure(): void
    {
        $this->expectOutputString('');
        $original = new \RuntimeException('Private downstream details', 599);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $services = container(JsonErrorHandlerServiceProvider::class, $this->application($reporter));

        /** @var JsonThrowableResponseFactory $pure */
        $pure = $services->get(JsonThrowableResponseFactory::class);
        self::assertSame(
            '{"error":"Request failed"}',
            (string) $pure->createResponseFromThrowable($original)->getBody(),
        );

        /** @var ReportingThrowableResponseFactory $selected */
        $selected = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertSame($selected, $services->get(ReportingThrowableResponseFactory::class));
        $response = $selected->createResponseFromThrowable($original);
        self::assertSame(599, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getReasonPhrase());
        self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
    }

    /**
     * Config supplies a real bool before construction rather than a service-ID scalar fallback.
     *
     * @param bool $debug the explicit trusted application choice
     *
     * @dataProvider provideConfigSelectsTheRequestedDebugRepresentationCases
     */
    public function testConfigSelectsTheRequestedDebugRepresentation(bool $debug): void
    {
        $settings = config(['error_handler' => ['json' => ['debug' => $debug]]]);
        $configured = $settings->get('error_handler.json.debug', false);
        self::assertIsBool($configured);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable');
        $services = container(new JsonErrorHandlerServiceProvider($configured), $this->application($reporter));

        /** @var ThrowableResponseFactoryInterface $renderer */
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        $response = $renderer->createResponseFromThrowable(new \RuntimeException('Configured private details'));
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($debug, isset($payload['exception']));
        self::assertSame($debug ? 'no-store' : '', $response->getHeaderLine('Cache-Control'));

        if ($debug) {
            self::assertSame('Configured private details', $payload['exception']['message']);
        } else {
            self::assertSame(['error' => 'Request failed'], $payload);
        }
    }

    /**
     * Covers both real boolean values without deriving them from request/environment strings.
     *
     * @return array<string, array{bool}> explicit application configuration
     */
    public static function provideConfigSelectsTheRequestedDebugRepresentationCases(): iterable
    {
        return ['public' => [false], 'development' => [true]];
    }

    /** A strict application call rejects an invalid string before a container can fall back. */
    public function testStrictConstructorRejectsAStringDebugChoice(): void
    {
        $this->expectException(\TypeError::class);

        // @phpstan-ignore argument.type (Deliberately exercise PHP's strict constructor boundary.)
        new JsonErrorHandlerServiceProvider('false');
    }

    /** Separate providers are first-wins; a required override uses application-last aggregation. */
    public function testRequiredRendererOverrideUsesAnAggregateProvider(): void
    {
        $original = new \RuntimeException('Private original', 503);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $response = (new Psr17Factory())->createResponse(418);
        $override = $this->createMock(ThrowableResponseFactoryInterface::class);
        $override->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($original))->willReturn($response)
        ;
        $definitions = $this->application($reporter)->getFactories();
        $definitions[ThrowableResponseFactoryInterface::class] = new ServiceFactory($override);
        $application = new ArrayServiceProvider($definitions);
        $separate = container(JsonErrorHandlerServiceProvider::class, $application);

        /** @var ThrowableResponseFactoryInterface $firstWins */
        $firstWins = $separate->get(ThrowableResponseFactoryInterface::class);
        self::assertSame(
            '{"error":"Request failed"}',
            (string) $firstWins->createResponseFromThrowable($original)->getBody(),
        );

        $aggregated = container(new AggregateServiceProvider(new JsonErrorHandlerServiceProvider(), $application));

        /** @var ThrowableResponseFactoryInterface $lastWins */
        $lastWins = $aggregated->get(ThrowableResponseFactoryInterface::class);
        self::assertSame($override, $lastWins);
        self::assertSame($response, $lastWins->createResponseFromThrowable($original));
    }

    /**
     * Real container-created scopes preserve success, report once and restore callbacks on failure.
     *
     * @param string $stage success, a rendered failure or one failing collaborator
     *
     * @dataProvider provideMiddlewarePreservesReportingFailuresAndCallbacksCases
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testMiddlewarePreservesReportingFailuresAndCallbacks(string $stage): void
    {
        $this->expectOutputString('');
        $original = new \RuntimeException('Private downstream details', 503);
        $failure = new \LogicException('Private collaborator failure');
        $reports = [];
        $reporter = new CallableThrowableHandler(
            /**
             * Records the original privately and optionally fails before rendering.
             *
             * @param \Throwable $event the object supplied by the selected decorator
             */
            static function (\Throwable $event) use (&$reports, $stage, $failure): void {
                $reports[] = $event;

                if ('reporter' === $stage) {
                    throw $failure;
                }
            },
        );
        $messages = new Psr17Factory();
        $expected = $messages->createResponse('success' === $stage ? 204 : 503);
        $responses = $this->createMock(ResponseFactoryInterface::class);

        if ('success' === $stage || 'reporter' === $stage) {
            $responses->expects(self::never())->method('createResponse');
        } elseif ('renderer' === $stage) {
            $responses->expects(self::once())->method('createResponse')->willThrowException($failure);
        } else {
            $responses->expects(self::once())->method('createResponse')->with(503)->willReturn($expected);
        }

        $services = container(JsonErrorHandlerServiceProvider::class, $this->application($reporter, $responses));

        /** @var ErrorHandlerMiddlewareFactory $factory */
        $factory = $services->get(ErrorHandlerMiddlewareFactory::class);
        $middleware = $factory($services);
        $next = $this->createMock(RequestHandlerInterface::class);

        if ('success' === $stage) {
            $next->expects(self::once())->method('handle')->willReturn($expected);
        } else {
            $next->expects(self::once())->method('handle')->willThrowException($original);
        }

        /** @return bool preserves the surrounding error callback */
        $previousError = static fn(): bool => true;

        /**
         * Rejects uncaught delivery rather than letting it escape the test fixture.
         *
         * @param \Throwable $event an unexpected native exception callback event
         */
        $previousException = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected callback delivery.', 0, $event);
        };
        set_error_handler($previousError);
        set_exception_handler($previousException);
        $caught = null;
        $response = null;

        try {
            try {
                $response = $middleware->process($messages->createServerRequest('GET', '/provider'), $next);
            } catch (\Throwable $actual) {
                $caught = $actual;
            }

            self::assertSame($previousError, set_error_handler($previousError));
            restore_error_handler();
            self::assertSame($previousException, set_exception_handler($previousException));
            restore_exception_handler();
            self::assertSame('success' === $stage ? [] : [$original], $reports);

            if ('reporter' === $stage || 'renderer' === $stage) {
                self::assertNull($response);
                self::assertSame($failure, $caught);
            } else {
                self::assertNull($caught);
                self::assertInstanceOf(ResponseInterface::class, $response);

                if ('success' === $stage) {
                    self::assertSame($expected, $response);
                } else {
                    self::assertSame(503, $response->getStatusCode());
                    self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
                }
            }
        } finally {
            restore_error_handler();
            restore_exception_handler();
        }
    }

    /**
     * Describes independent results without transporting throwable traces between processes.
     *
     * @return array<string, array{string}> meaningful pipeline outcomes
     */
    public static function provideMiddlewarePreservesReportingFailuresAndCallbacksCases(): iterable
    {
        return [
            'success' => ['success'],
            'JSON response' => ['rendered'],
            'reporter failure' => ['reporter'],
            'renderer failure' => ['renderer'],
        ];
    }

    /**
     * Supplies the three external dependencies without library-default reporter discovery.
     *
     * @param ThrowableHandlerInterface     $reporter  the private diagnostic destination
     * @param null|ResponseFactoryInterface $responses an optional response collaborator
     *
     * @return ArrayServiceProvider application-owned PSR-17 and reporter services
     */
    private function application(
        ThrowableHandlerInterface $reporter,
        ?ResponseFactoryInterface $responses = null
    ): ArrayServiceProvider {
        $messages = new Psr17Factory();

        return new ArrayServiceProvider([
            ResponseFactoryInterface::class => new ServiceFactory($responses ?? $messages),
            StreamFactoryInterface::class => new ServiceFactory($messages),
            ThrowableHandlerInterface::class => new ServiceFactory($reporter),
        ]);
    }
}
