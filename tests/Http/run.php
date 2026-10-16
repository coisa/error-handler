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

use function CoiSA\ErrorHandler\Test\Harness\runProcess;

require __DIR__ . '/../Support/process.php';

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
$arguments = $argv ?? [];
foreach ($arguments as $argument) {
    if (0 === strpos($argument, '--autoload=')) {
        $autoload = substr($argument, strlen('--autoload='));
    }
}
if (!is_file($autoload)) {
    throw new RuntimeException('Install PSR dependencies or use --autoload=/absolute/path/vendor/autoload.php.');
}

$cases = [
    '/error' => [500, 'legacy captured: synthetic downstream failure'],
    '/unavailable' => [500, 'legacy captured: synthetic upstream address'],
    '/client-error' => [404, 'legacy captured: synthetic client failure'],
    '/type-error' => [500, 'legacy captured: synthetic type mismatch'],
];
$checkRetry = in_array('--check-port-retry', $arguments, true);
$owner = bin2hex(random_bytes(16));
$completed = false;
$retried = false;
for ($attempt = 1; $attempt <= 3; ++$attempt) {
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (false === $socket) {
        throw new RuntimeException('Could not reserve a loopback port: ' . $error);
    }
    $address = stream_socket_get_name($socket, false);
    if (false === $address) {
        fclose($socket);

        throw new RuntimeException('Could not inspect the loopback address.');
    }
    $holdReservation = $checkRetry && 1 === $attempt;
    if (!$holdReservation) {
        fclose($socket);
    }
    $readyDeadline = microtime(true) + 5;
    $started = false;

    /**
     * Requests fixture routes only after the owned server confirms its bind.
     *
     * @param string $stdout the server's bounded standard output
     * @param string $stderr the server's bounded diagnostic output
     *
     * @return bool whether every fixture response was verified
     */
    $tick = static function (
        string $stdout,
        string $stderr
    ) use (
        $address,
        $cases,
        $readyDeadline,
        $owner,
        &$started,
        &$completed
    ): bool {
        if (false === strpos($stderr, '(http://' . $address . ') started')) {
            if (microtime(true) >= $readyDeadline) {
                throw new RuntimeException('Fixture server did not start within five seconds.');
            }

            return false;
        }
        $started = true;

        foreach ($cases as $route => [$expectedStatus, $expectedBody]) {
            $request = runProcess([
                PHP_BINARY, '-n', '-d', 'memory_limit=16M', __DIR__ . '/request.php',
                'http://' . $address . $route,
            ], 2.0, 131072);
            if ('exit' !== $request['reason'] || 0 !== $request['exitCode'] || !$request['reaped']) {
                throw new RuntimeException('Fixture request failed or exceeded its deadline: ' . $route);
            }
            $response = json_decode($request['stdout'], true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($response) || !isset($response['headers'], $response['body'])) {
                throw new RuntimeException('Fixture request produced invalid observations.');
            }
            $headers = $response['headers'];
            $body = $response['body'];
            if (!is_array($headers) || !isset($headers[0]) || !is_string($headers[0])) {
                throw new RuntimeException('Fixture response did not include HTTP headers.');
            }
            if (
                !is_string($body) || strlen($body) > 65536
                || false === strpos($headers[0], ' ' . $expectedStatus . ' ')
                || $body !== $expectedBody || !in_array('X-Fixture-Reports: 1', $headers, true)
                || !in_array('X-Fixture-Owner: ' . $owner, $headers, true)
                || !in_array('X-Fixture-Callbacks-Restored: yes', $headers, true)
            ) {
                $observations = json_encode([$headers, $body]);

                throw new RuntimeException('Unexpected legacy response: ' . $route . ' ' . $observations);
            }
            printf("PASS HTTP %-14s status=%d legacy capture, callbacks restored\n", $route, $expectedStatus);
        }
        $completed = true;

        return true;
    };

    try {
        $result = runProcess([
            PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=32M',
            '-S', $address, __DIR__ . '/router.php',
        ], 20.0, 262144, $tick, ['ERROR_HANDLER_TEST_AUTOLOAD' => $autoload, 'ERROR_HANDLER_TEST_OWNER' => $owner]);
    } finally {
        if ($holdReservation) {
            fclose($socket);
        }
    }
    if ($completed) {
        break;
    }
    if ($started || 'exit' !== $result['reason'] || !$result['reaped']) {
        throw new RuntimeException('Loopback fixture failed or exceeded a bound: ' . json_encode($result));
    }
    $retried = true;
}

if (!$completed || 'completed' !== $result['reason'] || !$result['reaped']) {
    throw new RuntimeException('Loopback fixture failed or exceeded a bound: ' . json_encode($result));
}
if ($checkRetry) {
    if (!$retried || 2 !== $attempt) {
        throw new RuntimeException('The occupied-port self-check did not retry exactly once.');
    }
    printf("PASS HTTP occupied port rejected, child reaped and startup retried\n");
}
printf("PASS HTTP server terminated and reaped on PHP %s\n", PHP_VERSION);
