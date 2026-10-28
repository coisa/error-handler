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
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use CoiSA\ErrorHandler\ServiceProvider\Factory\ErrorHandlerMiddlewareFactory;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\NegotiatingThrowableResponseFactoryFactory;
use FastForward\Container\Factory\CallableFactory;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface;
use Whoops\Handler\PrettyPageHandler;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\AggregateServiceProvider;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
 */
#[CoversClass(ErrorHandlerMiddlewareFactory::class)]
#[CoversClass(ErrorHandlerServiceProvider::class)]
#[CoversClass(NegotiatingThrowableResponseFactoryFactory::class)]
final class NegotiatedErrorHandlerServiceProviderTest extends TestCase
{
    /** One cached service selects representations from the current request without reporting. */
    public function testOneProviderNegotiatesEachCurrentRequest(): void
    {
        $original = new \RuntimeException('Private fallback details', 503);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $services = container($this->application($reporter), ErrorHandlerServiceProvider::class);
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(NegotiatingThrowableResponseFactory::class, $renderer);
        self::assertSame($renderer, $services->get(ThrowableResponseFactoryInterface::class));
        $json = $renderer->createResponseFromThrowable($original, self::jsonRequest());
        $htmlRequest = self::jsonRequest()->withHeader('Accept', 'text/html');
        $html = $renderer->createResponseFromThrowable($original, $htmlRequest);
        $plain = $renderer->createResponseFromThrowable($original, self::jsonRequest()->withoutHeader('Accept'));
        self::assertSame('application/json', $json->getHeaderLine('Content-Type'));
        self::assertSame(
            self::publicProblem(503, 'Service Unavailable'),
            json_decode((string) $json->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
        self::assertSame('text/html; charset=UTF-8', $html->getHeaderLine('Content-Type'));
        self::assertStringContainsString('<!DOCTYPE html>', (string) $html->getBody());
        self::assertStringNotContainsString($original->getMessage(), (string) $html->getBody());
        self::assertSame('text/plain', $plain->getHeaderLine('Content-Type'));
        foreach ([$json, $html, $plain] as $response) {
            self::assertSame(503, $response->getStatusCode());
            self::assertSame('Accept', $response->getHeaderLine('Vary'));
        }
    }

    /**
     * Negotiates the actual UTF-8 HTML representation through the public provider composition.
     *
     * @param string $accept      the client's media preferences and charset-specific quality
     * @param string $contentType the permitted representation after applying the most specific range
     */
    #[DataProvider('provideNegotiationUsesTheHtmlRenderersUtf8RepresentationCases')]
    public function testNegotiationUsesTheHtmlRenderersUtf8Representation(string $accept, string $contentType): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $services = container($this->application($reporter), ErrorHandlerServiceProvider::class);
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(NegotiatingThrowableResponseFactory::class, $renderer);
        $failure = new \RuntimeException('Private charset-negotiation diagnostic', 503);
        $request = self::jsonRequest()->withHeader('Accept', $accept);

        $response = $renderer->createResponseFromThrowable($failure, $request);

        self::assertSame($contentType, $response->getHeaderLine('Content-Type'));
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
        self::assertStringNotContainsString($failure->getMessage(), (string) $response->getBody());
    }

    /** @return iterable<string, array{string, string}> charset-specific preferences for the built-in HTML candidate */
    public static function provideNegotiationUsesTheHtmlRenderersUtf8RepresentationCases(): iterable
    {
        yield 'no preference retains generic fallback' => ['', 'text/plain'];

        yield 'bare HTML accepts its UTF-8 representation' => ['text/html', 'text/html; charset=UTF-8'];

        yield 'explicit UTF-8' => ['text/html; charset=UTF-8', 'text/html; charset=UTF-8'];

        yield 'mixed media and parameter case' => ['TEXT/HTML; CHARSET=utf-8', 'text/html; charset=UTF-8'];

        yield 'specific HTML quality beats JSON' => [
            'text/html; charset=utf-8;q=1, application/json;q=0.5', 'text/html; charset=UTF-8',
        ];

        yield 'specific HTML quality loses to JSON' => [
            'text/html; charset=UTF-8;q=0.4, text/html;q=1, application/json;q=0.5', 'application/json',
        ];

        yield 'specific UTF-8 exclusion overrides broad HTML permission' => [
            'text/html; charset=utf-8;q=0, text/html;q=1, application/json;q=0.5', 'application/json',
        ];

        yield 'specific UTF-8 preference overrides broad HTML exclusion' => [
            'text/html; charset=UTF-8;q=1, text/html;q=0, application/json;q=0.5', 'text/html; charset=UTF-8',
        ];

        yield 'unavailable charset retains generic fallback' => ['text/html; charset=ISO-8859-1', 'text/plain'];
    }

    /** Installed optional Whoops services cannot replace the configured Symfony HTML default. */
    public function testDebugUsesSymfonyAndDoesNotResolveOptionalWhoops(): void
    {
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $definitions = $this->application($reporter)->getFactories();
        $definitions[PrettyPageHandler::class] = new CallableFactory(
            /**
             * @param ContainerInterface $container the unused synthetic resolver
             *
             * @return PrettyPageHandler this optional page must never be requested
             *
             * @throws \LogicException if default negotiation unexpectedly selects Whoops
             */
            static function (ContainerInterface $container): PrettyPageHandler {
                throw new \LogicException('Whoops must require explicit application selection.');
            },
        );
        $services = container(new ArrayServiceProvider($definitions), new ErrorHandlerServiceProvider(debug: true));
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(NegotiatingThrowableResponseFactory::class, $renderer);
        $original = new \RuntimeException('Private Symfony development diagnostic', 503);
        $json = $renderer->createResponseFromThrowable($original, self::jsonRequest());
        $html = $renderer->createResponseFromThrowable(
            $original,
            self::jsonRequest()->withHeader('Accept', 'text/html'),
        );

        self::assertSame('application/json', $json->getHeaderLine('Content-Type'));
        self::assertStringContainsString('"class":', (string) $json->getBody());
        self::assertSame('text/html; charset=UTF-8', $html->getHeaderLine('Content-Type'));
        self::assertStringContainsString($original->getMessage(), (string) $html->getBody());
        self::assertStringContainsString('<!DOCTYPE html>', (string) $html->getBody());
        self::assertSame('no-store', $json->getHeaderLine('Cache-Control'));
        self::assertSame('no-store', $html->getHeaderLine('Cache-Control'));
    }

    /** The explicitly composed JSON-first providers select pure public JSON without reporting. */
    public function testDefaultProfileSelectsTheCachedPureJsonRenderer(): void
    {
        $this->expectOutputString('');
        $original = new \RuntimeException('Private downstream details', 599);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable')->with(self::identicalTo($original));
        $services = container(
            $this->application($reporter),
            ErrorHandlerServiceProvider::class,
        );

        /** @var JsonThrowableResponseFactory $pure */
        $pure = $services->get(JsonThrowableResponseFactory::class);
        $pureResponse = $pure->createResponseFromThrowable($original, self::jsonRequest());
        self::assertSame('application/problem+json', $pureResponse->getHeaderLine('Content-Type'));
        self::assertSame(
            self::publicProblem(599, ''),
            json_decode((string) $pureResponse->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );

        /** @var ThrowableResponseFactoryInterface $selected */
        $selected = $services->get(ThrowableResponseFactoryInterface::class);
        self::assertInstanceOf(NegotiatingThrowableResponseFactory::class, $selected);
        $response = $selected->createResponseFromThrowable($original, self::jsonRequest());
        self::assertSame(599, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', $response->getReasonPhrase());
        self::assertSame(
            self::publicProblem(599, ''),
            json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Config supplies a real bool before construction rather than a service-ID scalar fallback.
     *
     * @param bool $debug the explicit trusted application choice
     */
    #[DataProvider('provideConfigSelectsTheRequestedDebugRepresentationCases')]
    public function testConfigSelectsTheRequestedDebugRepresentation(bool $debug): void
    {
        $settings = config(['error_handler' => ['json' => ['debug' => $debug]]]);
        $configured = $settings->get('error_handler.json.debug', false);
        self::assertIsBool($configured);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable');
        $services = container(
            $this->application($reporter),
            new ErrorHandlerServiceProvider($configured),
        );

        /** @var ThrowableResponseFactoryInterface $renderer */
        $renderer = $services->get(ThrowableResponseFactoryInterface::class);
        $response = $renderer->createResponseFromThrowable(
            new \RuntimeException('Configured private details'),
            self::jsonRequest(),
        );
        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);
        self::assertSame($debug, isset($payload['class']));
        self::assertSame($debug ? 'no-store' : '', $response->getHeaderLine('Cache-Control'));

        if ($debug) {
            self::assertSame(\RuntimeException::class, $payload['class']);
            self::assertSame('Configured private details', $payload['detail']);
        } else {
            self::assertSame(self::publicProblem(500, 'Internal Server Error'), $payload);
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
        $provider = new ErrorHandlerServiceProvider('false');

        self::fail('An invalid debug choice unexpectedly constructed ' . $provider::class);
    }

    /** Separate providers are first-wins; a required override uses application-last aggregation. */
    public function testRequiredRendererOverrideUsesAnAggregateProvider(): void
    {
        $original = new \RuntimeException('Private original', 503);
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::never())->method('handleThrowable')->with(self::identicalTo($original));
        $response = (new Psr17Factory())->createResponse(418);
        $override = $this->createMock(ThrowableResponseFactoryInterface::class);
        $override->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($original), self::isInstanceOf(ServerRequestInterface::class))
            ->willReturn($response)
        ;
        $definitions = $this->application($reporter)->getFactories();
        $definitions[ThrowableResponseFactoryInterface::class] = new ServiceFactory($override);
        $application = new ArrayServiceProvider($definitions);
        $separate = container(
            ErrorHandlerServiceProvider::class,
            $application,
        );

        /** @var ThrowableResponseFactoryInterface $firstWins */
        $firstWins = $separate->get(ThrowableResponseFactoryInterface::class);
        $firstResponse = $firstWins->createResponseFromThrowable($original, self::jsonRequest());
        self::assertSame(
            self::publicProblem(503, 'Service Unavailable'),
            json_decode((string) $firstResponse->getBody(), true, 512, JSON_THROW_ON_ERROR),
        );

        $aggregated = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            $application,
        ));

        /** @var ThrowableResponseFactoryInterface $lastWins */
        $lastWins = $aggregated->get(ThrowableResponseFactoryInterface::class);
        self::assertSame($override, $lastWins);
        self::assertSame($response, $lastWins->createResponseFromThrowable($original, self::jsonRequest()));
    }

    /**
     * Real container-created scopes preserve success, report once and restore callbacks on failure.
     *
     * @param string $stage success, a rendered failure or one failing collaborator
     */
    #[DataProvider('provideMiddlewarePreservesReportingFailuresAndCallbacksCases')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
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
             * @param \Throwable $event the object supplied by the scoped major handler
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

        $services = container(new AggregateServiceProvider(
            new ErrorHandlerServiceProvider(),
            $this->application($reporter, $responses),
        ));

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
                $response = $middleware->process(self::jsonRequest(), $next);
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
                    self::assertSame(
                        self::publicProblem(503, 'Service Unavailable'),
                        json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR),
                    );
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
     * Supplies the approved public schema without reproducing the renderer's normalization algorithm.
     *
     * @param int    $status the expected HTTP status for this case
     * @param string $title  the expected standard reason phrase
     *
     * @return array{type: string, title: string, status: int, detail: string} the public Problem Details document
     */
    private static function publicProblem(int $status, string $title): array
    {
        return ['type' => 'about:blank', 'title' => $title, 'status' => $status, 'detail' => 'Request failed'];
    }

    /** @return ServerRequestInterface the current synthetic JSON-only request */
    private static function jsonRequest(): ServerRequestInterface
    {
        return (new Psr17Factory())->createServerRequest('GET', '/provider')->withHeader('Accept', 'application/json');
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
        ?ResponseFactoryInterface $responses = null,
    ): ArrayServiceProvider {
        $messages = new Psr17Factory();

        return new ArrayServiceProvider([
            ResponseFactoryInterface::class => new ServiceFactory($responses ?? $messages),
            StreamFactoryInterface::class => new ServiceFactory($messages),
            ThrowableHandlerInterface::class => new ServiceFactory($reporter),
        ]);
    }
}
