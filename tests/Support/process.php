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

namespace CoiSA\ErrorHandler\Test\Harness;

/**
 * Validates native status inspection for an owned fixture process.
 *
 * @param resource $process the live process handle
 *
 * @return array{running: bool, exitcode: int} the fields used by the bounded runner
 */
function processStatus($process): array
{
    /**
     * PHP 7.4 returns false when the process resource cannot be resolved.
     *
     * @var array{running: bool, exitcode: int}|false $status
     */
    $status = proc_get_status($process);
    if (false === $status) {
        throw new \RuntimeException('Could not inspect fixture process.');
    }

    return ['running' => $status['running'], 'exitcode' => $status['exitcode']];
}

/**
 * Reads bounded chunks without allowing a noisy child to exhaust parent memory.
 *
 * @param array<int, resource> $pipes  the child's output pipes
 * @param string               $stdout collected standard output
 * @param string               $stderr collected standard error
 * @param int                  $limit  maximum combined bytes, plus one byte to detect overflow
 */
function readOutput(array $pipes, string &$stdout, string &$stderr, int $limit): void
{
    foreach ([1, 2] as $index) {
        $remaining = $limit + 1 - \strlen($stdout) - \strlen($stderr);
        if ($remaining <= 0) {
            return;
        }

        $chunk = stream_get_contents($pipes[$index], min(8192, $remaining));
        if (false === $chunk) {
            throw new \RuntimeException('Could not read fixture output.');
        }

        if (1 === $index) {
            $stdout .= $chunk;
        } else {
            $stderr .= $chunk;
        }
    }
}

/**
 * Runs an owned fixture child and always closes its pipes and process handle.
 *
 * @param list<string>          $command     an argument vector, never a shell command
 * @param float                 $timeout     maximum active lifetime in seconds
 * @param int                   $limit       maximum combined captured output bytes
 * @param null|callable(): bool $tick        optional bounded loopback work; true stops the fixture
 * @param array<string, string> $environment the child's explicit environment
 *
 * @return array{exitCode: int, stdout: string, stderr: string, reason: string, reaped: bool, elapsed: float}
 */
function runProcess(
    array $command,
    float $timeout = 5.0,
    int $limit = 262144,
    ?callable $tick = null,
    array $environment = []
): array {
    if ($timeout <= 0 || $limit < 1) {
        throw new \InvalidArgumentException('Fixture bounds must be positive.');
    }

    $started = microtime(true);
    $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptors, $pipes, null, $environment);
    if (!\is_resource($process)) {
        throw new \RuntimeException('Could not start fixture process.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = $stderr = '';
    $exitCode = -1;
    $reason = 'exit';
    $running = true;

    try {
        while (true) {
            readOutput($pipes, $stdout, $stderr, $limit);
            if (\strlen($stdout) + \strlen($stderr) > $limit) {
                $reason = 'output-limit';

                break;
            }

            $status = processStatus($process);
            $running = $status['running'];
            if ($status['exitcode'] >= 0) {
                $exitCode = $status['exitcode'];
            }
            if (!$running) {
                break;
            }
            if (microtime(true) - $started >= $timeout) {
                $reason = 'timeout';

                break;
            }
            if (null !== $tick && $tick()) {
                $reason = 'completed';

                break;
            }

            usleep(1000);
        }
    } finally {
        try {
            if ($running) {
                proc_terminate($process);
                $deadline = microtime(true) + 0.25;
                do {
                    $status = processStatus($process);
                    $running = $status['running'];
                    if ($status['exitcode'] >= 0) {
                        $exitCode = $status['exitcode'];
                    }
                    if ($running) {
                        usleep(1000);
                    }
                } while ($running && microtime(true) < $deadline);

                if ($running) {
                    proc_terminate($process, 9);
                    do {
                        $status = processStatus($process);
                        $running = $status['running'];
                        usleep(1000);
                    } while ($running && microtime(true) < $deadline + 1);
                }
            }
            do {
                $previousBytes = \strlen($stdout) + \strlen($stderr);
                readOutput($pipes, $stdout, $stderr, $limit);
                $capturedBytes = \strlen($stdout) + \strlen($stderr);
            } while ($previousBytes < $capturedBytes && $capturedBytes <= $limit);
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            $closed = proc_close($process);
            if ($exitCode < 0) {
                $exitCode = $closed;
            }
        }
    }

    if (\strlen($stdout) + \strlen($stderr) > $limit) {
        $reason = 'output-limit';
    }

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
        'reason' => $reason,
        'reaped' => !$running,
        'elapsed' => microtime(true) - $started,
    ];
}
