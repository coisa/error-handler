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

use CoiSA\ErrorHandler\Example\ConfiguredErrorHandlerFactory;
use CoiSA\ErrorHandler\Example\FailingThrowableHandler;
use CoiSA\ErrorHandler\Example\PublicThrowableResponseFactory;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\Process\Process;

/**
 * Verifies the shipped application examples without attributing them to core coverage.
 *
 * @internal
 *
 * @coversNothing
 */
final class ConsumerSkillExamplesTest extends TestCase
{
    /**
     * Configuration overrides select the actual reporter before creating a fresh scope.
     */
    public function testExplicitOverridesWinAndFactoryScopesAreDistinct(): void
    {
        $default = new RecordingThrowableHandler();
        $selected = new RecordingThrowableHandler();
        $factory = new ConfiguredErrorHandlerFactory();
        $configuration = ['reporter' => $default];
        $overrides = ['reporter' => $selected];

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

    /**
     * Missing and wrong-type reporters fail before the application starts.
     *
     * @param array<string, mixed> $configuration the malformed application configuration
     *
     * @dataProvider provideInvalidConfigurationIsRejectedCases
     */
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
     * Private diagnostics and exception codes do not become the application's public body.
     */
    public function testPublicResponseReportsOnceWithoutLeakingDiagnostics(): void
    {
        $reporter = new RecordingThrowableHandler();
        $http = new Psr17Factory();
        $renderer = new PublicThrowableResponseFactory($reporter, $http, $http);
        $failure = new \RuntimeException('Private secret detail', 404);

        $response = $renderer->createResponseFromThrowable($failure);

        self::assertSame([$failure], $reporter->getThrowables());
        self::assertSame(500, $response->getStatusCode());
        self::assertSame('Internal Server Error', $response->getReasonPhrase());
        self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
    }

    /**
     * A failed report stops response construction and retains the original failure object.
     */
    public function testReporterFailurePreventsPublicResponseConstruction(): void
    {
        $failure = new \RuntimeException('Synthetic reporting failure');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::never())->method('createResponse');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');

        $renderer = new PublicThrowableResponseFactory(
            new FailingThrowableHandler($failure),
            $responses,
            $streams,
        );

        try {
            $renderer->createResponseFromThrowable(new \RuntimeException('Original failure'));

            self::fail('Expected reporting failure before PSR-17 construction.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
        }
    }

    /**
     * A response-factory failure propagates after the original was reported exactly once.
     */
    public function testResponseFactoryFailureDoesNotRetryTheReporter(): void
    {
        $reporter = new RecordingThrowableHandler();
        $original = new \RuntimeException('Original request failure');
        $failure = new \RuntimeException('Synthetic response factory failure');
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $responses->expects(self::once())->method('createResponse')->with(500)->willThrowException($failure);
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $renderer = new PublicThrowableResponseFactory($reporter, $responses, $streams);

        try {
            $renderer->createResponseFromThrowable($original);

            self::fail('Expected the response-factory failure to propagate.');
        } catch (\Throwable $observed) {
            self::assertSame($failure, $observed);
            self::assertSame([$original], $reporter->getThrowables());
        }
    }

    /**
     * Execute the actual programs; HTTP callback installation stays in its own child.
     *
     * @param string       $file      the shipped example program
     * @param string       $output    the exact success observation
     * @param list<string> $arguments explicit synthetic application inputs
     *
     * @dataProvider provideShippedProgramsUseTheExplicitConsumerAutoloaderCases
     */
    public function testShippedProgramsUseTheExplicitConsumerAutoloader(
        string $file,
        string $output,
        array $arguments = []
    ): void {
        $root = \dirname(__DIR__, 2);
        $process = new Process([
            PHP_BINARY,
            '-n',
            '-d', 'memory_limit=64M',
            $root . '/resources/skills/coisa-error-handler/examples/' . $file,
            $root . '/vendor/autoload.php',
            ...$arguments,
        ], $root, null, null, 5.0);

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
     * @return array<string, array{string, string, list<string>}> names, observations and explicit inputs
     */
    public static function provideShippedProgramsUseTheExplicitConsumerAutoloaderCases(): iterable
    {
        return [
            'configuration' => ['configured.php', 'configured reporting: original delivered once', []],
            'container' => [
                'container.php', 'legacy container: application reporter, caching and fresh scope passed', [],
            ],
            'HTTP' => ['http.php', 'HTTP: private report once and fixed public response passed', []],
            'JSON public' => [
                'json.php', 'JSON public: original reported once and explicit response passed', ['public'],
            ],
            'JSON debug' => ['json.php', 'JSON debug: original reported once and explicit response passed', ['debug']],
            'provider public' => [
                'provider-json.php', 'Interop JSON public: independent providers and fresh scope passed', ['public'],
            ],
            'provider debug' => [
                'provider-json.php', 'Interop JSON debug: independent providers and fresh scope passed', ['debug'],
            ],
        ];
    }
}
