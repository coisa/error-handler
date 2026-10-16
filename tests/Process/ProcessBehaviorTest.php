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

namespace CoiSA\ErrorHandler\Test\Process;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * Verifies native termination outside PHPUnit's result protocol using public process control.
 *
 * These are process tests; their children do not contribute parent unit coverage.
 *
 * @internal
 *
 * @coversNothing
 */
final class ProcessBehaviorTest extends TestCase
{
    /**
     * Verifies actual exit status, native reporting order and emitted exception metadata.
     *
     * @param string      $case     the owned fixture selection
     * @param int         $exit     the established2.x native exit status
     * @param string[]    $events   expected event names in actual delivery order
     * @param null|string $class    the first reported Throwable type when relevant
     * @param null|int    $severity the fatal severity when relevant
     * @param bool        $native   whether PHP emits its own fatal diagnostic
     *
     * @dataProvider provideNativeLifecycleCases
     */
    public function testNativeLifecycle(
        string $case,
        int $exit,
        array $events,
        ?string $class = null,
        ?int $severity = null,
        bool $native = false
    ): void {
        $process = new Process([
            PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-d', 'display_errors=stderr',
            '-d', 'log_errors=0', '-d', 'error_reporting=-1',
            \dirname(__DIR__) . '/Fixtures/lifecycle.php', $case,
        ], null, null, null, 2.0);
        $bytes = 0;
        $limited = false;

        try {
            $process->run(
                /**
                 * Bounds retained fixture output while Symfony owns pipe/child management.
                 *
                 * @param string $type  the standard pipe identifier
                 * @param string $chunk the new fixture bytes
                 *
                 * @throws \LengthException when a fixture exceeds64KiB
                 */
                static function (string $type, string $chunk) use (&$bytes, &$limited): void {
                    $bytes += \strlen($chunk);
                    if ($bytes > 65536 && !$limited) {
                        $limited = true;

                        throw new \LengthException('Lifecycle fixture exceeded64KiB.');
                    }
                },
            );
        } finally {
            $process->stop(0);
        }
        self::assertFalse($process->isRunning());
        self::assertSame($exit, $process->getExitCode());
        $observed = [];
        foreach (array_filter(explode("\n", trim($process->getOutput()))) as $line) {
            $event = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($event);
            self::assertArrayHasKey('event', $event);
            self::assertIsString($event['event']);
            $observed[] = $event;
        }
        self::assertSame($events, array_column($observed, 'event'));
        if (null !== $class) {
            self::assertSame($class, $observed[0]['class'] ?? null);
        }
        if (null !== $severity) {
            self::assertSame($severity, $observed[0]['severity'] ?? null);
        }
        if ($native) {
            self::assertNotSame('', $process->getErrorOutput());
        } else {
            self::assertSame('', $process->getErrorOutput());
        }
    }

    /** @return array<string, array{string, int, string[], null|string, null|int, bool}> real native lifecycle cases */
    public static function provideNativeLifecycleCases(): iterable
    {
        $fatal = 'CoiSA\ErrorHandler\Exception\ErrorException';

        return [
            'uncaught exception exits zero' => ['exception', 0, ['error'], \RuntimeException::class, null, false],
            'uncaught engine Error' => ['error', 0, ['error'], \Error::class, null, false],
            'uncaught TypeError' => ['type-error', 0, ['error'], \TypeError::class, null, false],
            'uncaught ParseError' => ['parse-error', 0, ['error'], \ParseError::class, null, false],
            'compile fatal' => ['compile-fatal', 255, ['error'], $fatal, E_COMPILE_ERROR, true],
            'nested fatal reports twice' => ['nested-fatal', 255, ['error', 'error'], $fatal, E_COMPILE_ERROR, true],
            'memory fatal' => ['memory-limit', 255, ['error'], $fatal, E_ERROR, true],
            'shutdown queue retains registrations' => ['shutdown-repeated', 0,
                ['shutdown', 'shutdown', 'shutdown', 'shutdown'], null, null, false],
            'native previous callbacks restored' => ['restore', 42,
                ['error', 'previous-error', 'previous-exception'], $fatal, E_USER_WARNING, false],
            'reporter failure reenters' => ['reporter-throws', 255,
                ['reporter-called', 'reporter-called', 'reporter-called'], null, null, true],
            'actual shutdown adapter failure' => [
                'shutdown-throws', 0, ['error'], \RuntimeException::class, null, false,
            ],
        ];
    }

    /**
     * Verifies protected and throwing native output handlers without corrupting PHPUnit's protocol.
     *
     * @param string $case      the hazardous native fixture selection
     * @param int    $remaining the established number of remaining scopes above entry
     * @param int    $callbacks the expected output callback count before shutdown
     *
     * @dataProvider provideHazardousOutputHandlersCases
     */
    public function testHazardousOutputHandlers(string $case, int $remaining, int $callbacks): void
    {
        $process = new Process([
            PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
            \dirname(__DIR__) . '/Fixtures/output-buffer.php', $case,
        ], null, null, null, 2.0);
        $bytes = 0;
        $limited = false;

        try {
            $process->run(
                /**
                 * Caps both fixture pipes while Symfony manages their lifetime.
                 *
                 * @param string $type  the standard pipe identifier
                 * @param string $chunk the observed output bytes
                 *
                 * @throws \LengthException when fixture output exceeds64KiB
                 */
                static function (string $type, string $chunk) use (&$bytes, &$limited): void {
                    $bytes += \strlen($chunk);
                    if ($bytes > 65536 && !$limited) {
                        $limited = true;

                        throw new \LengthException('Output fixture exceeded64KiB.');
                    }
                },
            );
        } finally {
            $process->stop(0);
        }
        self::assertFalse($process->isRunning());
        if ('terminates' === $case) {
            self::assertSame(23, $process->getExitCode());
            self::assertStringContainsString('terminating callback', $process->getErrorOutput());
            self::assertStringNotContainsString('unexpected caller continuation', $process->getErrorOutput());

            return;
        }
        self::assertSame(0, $process->getExitCode());
        self::assertSame('C0:C1:', $process->getOutput());
        $event = json_decode(trim($process->getErrorOutput()), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($event);
        self::assertSame([
            'originalSame' => true, 'primarySame' => true, 'remaining' => $remaining,
            'callbacks' => $callbacks, 'callerCallbacks' => 0,
        ], $event);
    }

    /** @return array<string, array{string, int, int}> only genuine hazardous native operations */
    public static function provideHazardousOutputHandlersCases(): iterable
    {
        return [
            'protected caller' => ['protected-caller', 0, 0],
            'protected nested scope' => ['protected-top', 2, 0],
            'cleanup callback throws' => ['cleanup-throws', 1, 1],
            'capture callback throws' => ['capture-throws', 0, 1],
            'cleanup callback exits' => ['terminates', 0, 0],
        ];
    }
}
