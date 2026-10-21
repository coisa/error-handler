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

namespace CoiSA\ErrorHandler\Test\Unit\Handler;

use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * Verifies original terminal diagnostics, raw Console delivery and native stderr ownership.
 *
 * @internal
 */
#[CoversClass(SymfonyCliThrowableHandler::class)]
final class SymfonyCliThrowableHandlerTest extends TestCase
{
    /** Every diagnostic must use its selected destination rather than ambient stdout. */
    protected function setUp(): void
    {
        $this->expectOutputString('');
    }

    /** Real BufferedOutput preserves diagnostic markup and leaves the original throwable unchanged. */
    public function testBufferedOutputReceivesTheOriginalDiagnosticAsRawText(): void
    {
        $failure = $this->createDiagnostic();
        $trace = $failure->getTrace();
        $previous = $failure->getPrevious();
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $before = self::callbacks();
        $handler = new SymfonyCliThrowableHandler($output);
        self::assertSame('', $output->fetch());
        self::assertSame($before, self::callbacks());

        $handler->handleThrowable($failure);
        $text = $output->fetch();

        self::assertStringContainsString('<error>CLI synthetic failure</error>', $text);
        self::assertStringContainsString('CLI previous cause', $text);
        self::assertStringContainsString('RuntimeException', $text);
        self::assertStringContainsString('createDiagnostic', $text);
        self::assertStringContainsString(basename($failure->getFile()), $text);
        self::assertSame($trace, $failure->getTrace());
        self::assertSame($previous, $failure->getPrevious());
        self::assertSame(503, $failure->getCode());
        self::assertSame($before, self::callbacks());
    }

    /** ConsoleOutput's separate error channel receives one write while stdout remains untouched. */
    public function testConsoleOutputUsesItsErrorChannelWithoutParsingMarkup(): void
    {
        $errorOutput = $this->createMock(OutputInterface::class);
        $errorOutput->expects(self::once())->method('write')->with(
            self::stringContains('Console channel failure'),
            false,
            OutputInterface::OUTPUT_RAW,
        );
        $errorOutput->expects(self::never())->method('writeln');
        $console = $this->createMock(ConsoleOutputInterface::class);
        $console->expects(self::once())->method('getErrorOutput')->willReturn($errorOutput);
        $console->expects(self::never())->method('write');
        $console->expects(self::never())->method('writeln');

        (new SymfonyCliThrowableHandler($console))->handleThrowable(new \Error('Console channel failure'));
    }

    /** Output failures propagate unchanged without a retry or native callback mutation. */
    public function testOutputFailureRemainsIdentical(): void
    {
        $failure = new \LogicException('Console transport failed');
        $output = $this->createMock(OutputInterface::class);
        $output->expects(self::once())->method('write')->with(
            self::stringContains('Original terminal diagnostic'),
            false,
            OutputInterface::OUTPUT_RAW,
        )->willThrowException($failure);
        $output->expects(self::never())->method('writeln');
        $before = self::callbacks();

        try {
            (new SymfonyCliThrowableHandler($output))->handleThrowable(
                new \RuntimeException('Original terminal diagnostic'),
            );
            self::fail('The application output failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame($before, self::callbacks());
    }

    /** A failed error-channel lookup cannot fall back to standard output or native stderr. */
    public function testErrorChannelFailureRemainsIdentical(): void
    {
        $failure = new \LogicException('Error channel unavailable');
        $console = $this->createMock(ConsoleOutputInterface::class);
        $console->expects(self::once())->method('getErrorOutput')->willThrowException($failure);
        $console->expects(self::never())->method('write');
        $console->expects(self::never())->method('writeln');

        try {
            (new SymfonyCliThrowableHandler($console))->handleThrowable(
                new \RuntimeException('Original terminal diagnostic'),
            );
            self::fail('The error-channel failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Sequential calls create current diagnostics without retaining the previous Throwable or request. */
    public function testSequentialDiagnosticsDoNotRetainEarlierFailures(): void
    {
        $output = new BufferedOutput();
        $handler = new SymfonyCliThrowableHandler($output);
        $handler->handleThrowable(new \RuntimeException('FIRST_CLI_DIAGNOSTIC'));
        $first = $output->fetch();
        $handler->handleThrowable(new \Error('SECOND_CLI_DIAGNOSTIC'));
        $second = $output->fetch();

        self::assertStringContainsString('FIRST_CLI_DIAGNOSTIC', $first);
        self::assertStringContainsString('SECOND_CLI_DIAGNOSTIC', $second);
        self::assertStringNotContainsString('FIRST_CLI_DIAGNOSTIC', $second);
    }

    /** Native stderr delivery works without loading the optional Console package or touching stdout. */
    public function testNativeStderrDoesNotRequireConsoleOrTerminateTheCaller(): void
    {
        $autoload = \dirname(__DIR__, 3) . '/vendor/autoload.php';
        $code = <<<'PHP'
            $loader = require $argv[1];
            $loader->setPsr4('Symfony\\Component\\Console\\', []);
            spl_autoload_register(static function (string $class): void {
                if (str_starts_with($class, 'Symfony\\Component\\Console\\')) {
                    throw new LogicException('Optional Console must not be loaded for native stderr.');
                }
            }, true, true);
            $handler = new CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler();
            $handler->handleThrowable(new RuntimeException('NATIVE_CLI_DIAGNOSTIC'));
            $handler->handleThrowable(new Error('SECOND_NATIVE_CLI_DIAGNOSTIC'));
            echo "caller continued\n";
            PHP;
        $environment = array_fill_keys(array_keys(getenv()), false);
        $process = new Process([
            PHP_BINARY, '-d', 'memory_limit=64M', '-d', 'display_errors=stderr',
            '-d', 'log_errors=0', '-d', 'error_reporting=-1', '-r', $code, $autoload,
        ], null, $environment, null, 2.0);
        $bytes = 0;

        try {
            $process->run(
                /**
                 * Retains the existing native-process output bound without storing ambient HTML or environment data.
                 *
                 * @param string $type  the stdout or stderr pipe
                 * @param string $chunk newly received diagnostic bytes
                 *
                 * @throws \LengthException when the synthetic fixture exceeds 64 KiB
                 */
                static function (string $type, string $chunk) use (&$bytes): void {
                    $bytes += \strlen($chunk);
                    if ($bytes > 65536) {
                        throw new \LengthException('CLI diagnostic fixture exceeded 64 KiB.');
                    }
                },
            );
        } finally {
            $process->stop(0);
        }

        self::assertFalse($process->isRunning());
        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        self::assertSame("caller continued\n", $process->getOutput());
        self::assertStringContainsString('NATIVE_CLI_DIAGNOSTIC', $process->getErrorOutput());
        self::assertStringContainsString('SECOND_NATIVE_CLI_DIAGNOSTIC', $process->getErrorOutput());
        self::assertStringNotContainsString('Optional Console must not be loaded', $process->getErrorOutput());
        self::assertStringNotContainsString('Warning:', $process->getErrorOutput());
    }

    /** @return \RuntimeException a real named frame and previous cause without application credentials */
    private function createDiagnostic(): \RuntimeException
    {
        return new \RuntimeException(
            '<error>CLI synthetic failure</error>',
            503,
            new \RuntimeException('CLI previous cause'),
        );
    }

    /**
     * Reads active native callbacks while restoring both temporary probe stacks.
     *
     * @return array{null|callable, null|callable} current error and exception handlers
     */
    private static function callbacks(): array
    {
        $error = set_error_handler(static fn(): bool => false);
        restore_error_handler();
        $exception = set_exception_handler(
            /** @param \Throwable $event the unused native probe input */
            static function (\Throwable $event): void {
            },
        );
        restore_exception_handler();

        return [$error, $exception];
    }
}
