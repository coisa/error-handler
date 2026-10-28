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

use CoiSA\ErrorHandler\ErrorHandlerInterface;
use CoiSA\ErrorHandler\Example\ConfiguredErrorHandlerFactory;
use CoiSA\ErrorHandler\Example\FailingThrowableHandler;
use CoiSA\ErrorHandler\Example\PublicThrowableResponseFactory;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use CoiSA\ErrorHandler\Example\ThrowingRequestHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Process\Process;

/**
 * Verifies the shipped application examples without attributing them to core coverage.
 *
 * @internal
 */
#[CoversNothing]
final class ConsumerSkillExamplesTest extends TestCase
{
    /**
     * Configuration overrides select the actual reporter before creating a fresh scope.
     *
     * @param bool $callback whether to select the reporter's first-class method callable
     */
    #[DataProvider('provideExplicitOverridesWinAndFactoryScopesAreDistinctCases')]
    public function testExplicitOverridesWinAndFactoryScopesAreDistinct(bool $callback): void
    {
        $default = new RecordingThrowableHandler();
        $selected = new RecordingThrowableHandler();
        $factory = new ConfiguredErrorHandlerFactory();
        $configuration = ['reporter' => $default];
        $overrides = ['reporter' => $callback ? $selected->handleThrowable(...) : $selected];

        $first = $factory($configuration, $overrides);
        $second = $factory($configuration, $overrides);
        $failure = new \RuntimeException('Synthetic failure');

        self::assertNotSame($first, $second);
        self::assertSame([], $selected->getThrowables());

        $first->handleThrowable($failure);
        $second->handleThrowable($failure);

        self::assertSame([], $default->getThrowables());
        self::assertSame([$failure, $failure], $selected->getThrowables());
        self::assertSame($default, $configuration['reporter']);
    }

    /** @return array<string, array{bool}> interface and callable application override forms */
    public static function provideExplicitOverridesWinAndFactoryScopesAreDistinctCases(): iterable
    {
        return ['interface' => [false], 'callable' => [true]];
    }

    /**
     * Missing and wrong-type reporters fail before the application starts.
     *
     * @param array<string, mixed> $configuration the malformed application configuration
     */
    #[DataProvider('provideInvalidConfigurationIsRejectedCases')]
    public function testInvalidConfigurationIsRejected(array $configuration): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ConfiguredErrorHandlerFactory())($configuration);
    }

    /**
     * Supplies distinct absent, null and wrong-type input boundaries.
     *
     * @return array<string, array{array<string, mixed>}> invalid application inputs
     */
    public static function provideInvalidConfigurationIsRejectedCases(): iterable
    {
        return [
            'missing' => [[]],
            'null' => [['reporter' => null]],
            'wrong type' => [['reporter' => 'not a reporter']],
        ];
    }

    /**
     * Configuration does not hide an executable reporter failure.
     */
    public function testConfiguredReporterFailurePreservesItsIdentity(): void
    {
        $failure = new \RuntimeException('Synthetic transport failure');
        $errors = (new ConfiguredErrorHandlerFactory())([
            'reporter' => new FailingThrowableHandler($failure),
        ]);

        try {
            $errors->handleThrowable(new \RuntimeException('Original application failure'));

            self::fail('Expected the reporter failure to propagate.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * The application renderer is pure and never copies diagnostic details or codes.
     */
    public function testPublicResponseIsPureWithoutLeakingDiagnostics(): void
    {
        $http = new Psr17Factory();
        $renderer = new PublicThrowableResponseFactory($http, $http);
        $failure = new \RuntimeException('Private secret detail', 404);

        $response = $renderer->createResponseFromThrowable($failure, new ServerRequest('GET', 'https://example.test/'));

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getReasonPhrase());
        self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * A failed middleware report stops the pure renderer before PSR-17 construction.
     */
    public function testReporterFailurePreventsPublicResponseConstruction(): void
    {
        $original = new \RuntimeException('Original failure');
        $failure = new \RuntimeException('Synthetic reporting failure');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $scope = $this->createMockForIntersectionOfInterfaces([
            ErrorHandlerInterface::class,
            ThrowableHandlerInterface::class,
        ]);
        self::assertInstanceOf(ErrorHandlerInterface::class, $scope);
        self::assertInstanceOf(ThrowableHandlerInterface::class, $scope);
        $scope->expects(self::once())->method('register');
        $scope->expects(self::once())->method('unregister');
        $scope->expects(self::once())->method('handleThrowable')->with($original)->willThrowException($failure);
        $middleware = new ErrorHandlerMiddleware($scope, new PublicThrowableResponseFactory($responses, $streams));
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.test/');

        try {
            $middleware->process($request, new ThrowingRequestHandler($original));

            self::fail('Expected reporting failure before PSR-17 construction.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * A response-factory failure propagates after one middleware report, without retry.
     */
    public function testResponseFactoryFailureDoesNotRetryTheReporter(): void
    {
        $original = new \RuntimeException('Original request failure');
        $failure = new \RuntimeException('Synthetic response factory failure');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willThrowException($failure);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $scope = $this->createMockForIntersectionOfInterfaces([
            ErrorHandlerInterface::class,
            ThrowableHandlerInterface::class,
        ]);
        self::assertInstanceOf(ErrorHandlerInterface::class, $scope);
        self::assertInstanceOf(ThrowableHandlerInterface::class, $scope);
        $scope->expects(self::once())->method('register');
        $scope->expects(self::once())->method('unregister');
        $scope->expects(self::once())->method('handleThrowable')->with($original);
        $middleware = new ErrorHandlerMiddleware($scope, new PublicThrowableResponseFactory($responses, $streams));
        $request = (new Psr17Factory())->createServerRequest('GET', 'http://example.test/');

        try {
            $middleware->process($request, new ThrowingRequestHandler($original));

            self::fail('Expected the response-factory failure to propagate.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * Execute the actual programs; HTTP callback installation stays in its own child.
     *
     * @param string       $file      the shipped example program
     * @param list<string> $arguments the explicit synthetic program choices
     * @param string       $output    the exact success observation
     */
    #[DataProvider('provideShippedProgramsUseTheExplicitConsumerAutoloaderCases')]
    public function testShippedProgramsUseTheExplicitConsumerAutoloader(
        string $file,
        array $arguments,
        string $output,
    ): void {
        $root = \dirname(__DIR__, 2);
        $process = new Process(array_merge([
            PHP_BINARY,
            '-d', 'memory_limit=64M',
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
            '-d', 'error_reporting=-1',
            $root . '/resources/skills/coisa-error-handler/examples/' . $file,
            $root . '/vendor/autoload.php',
        ], $arguments), $root, null, null, 5.0);

        try {
            $process->run();

            self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            self::assertSame($output . PHP_EOL, $process->getOutput());
            self::assertSame('', $process->getErrorOutput());
        } finally {
            $process->stop(0);
        }
    }

    /**
     * Supplies complete programs without sharing mutable application collaborators.
     *
     * @return array<string, array{string, list<string>, string}> programs, arguments and expected output
     */
    public static function provideShippedProgramsUseTheExplicitConsumerAutoloaderCases(): iterable
    {
        return [
            'configuration' => ['configured.php', [], 'configured reporting: original delivered once'],
            'container' => [
                'container.php', [],
                'Interop provider: application reporter, caching and fresh scope passed',
            ],
            'HTTP' => ['http.php', [], 'HTTP: private report once and fixed public response passed'],
            'JSON public' => [
                'json.php', ['public'],
                'JSON public: original reported once and explicit response passed',
            ],
            'JSON debug' => [
                'json.php', ['debug'],
                'JSON debug: original reported once and explicit response passed',
            ],
            'HTML public' => [
                'html.php', ['public'],
                'HTTP HTML public: Symfony rendering and one original report passed',
            ],
            'HTML debug' => [
                'html.php', ['debug'],
                'HTTP HTML debug: Symfony rendering and one original report passed',
            ],
            'Whoops manual' => [
                'whoops.php', [],
                'Whoops HTML: explicit development page and one original report passed',
            ],
            'JSON provider public' => [
                'provider-json.php', ['public'],
                'Interop JSON public: single provider and fresh scope passed',
            ],
            'JSON provider debug' => [
                'provider-json.php', ['debug'],
                'Interop JSON debug: single provider and fresh scope passed',
            ],
            'Whoops provider public' => [
                'provider-whoops.php', ['public'],
                'Interop HTML public: Symfony renderer and original reports passed',
            ],
            'Whoops provider default' => [
                'provider-whoops.php', ['default'],
                'Interop Whoops default: single provider, shared page and original reports passed',
            ],
            'Whoops provider configured' => [
                'provider-whoops.php', ['configured'],
                'Interop Whoops configured: single provider, shared page and original reports passed',
            ],
            'Symfony CLI direct' => [
                'symfony-cli.php', ['direct'],
                'Symfony CLI direct: one terminal diagnostic and explicit capture ownership passed',
            ],
            'Symfony CLI provider' => [
                'symfony-cli.php', ['provider'],
                'Symfony CLI provider: one terminal diagnostic and explicit capture ownership passed',
            ],
            'Symfony CLI console output' => [
                'symfony-cli.php', ['console'],
                'Symfony CLI console: one terminal diagnostic and explicit capture ownership passed',
            ],
        ];
    }
}
