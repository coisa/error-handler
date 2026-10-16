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
foreach ($argv as $argument) {
    if (0 === strpos($argument, '--autoload=')) {
        $autoload = substr($argument, strlen('--autoload='));
    }
}
if (!is_file($autoload)) {
    throw new RuntimeException('Install PSR dependencies or use --autoload=/absolute/path/vendor/autoload.php.');
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if (false === $socket) {
    throw new RuntimeException('Could not reserve a loopback port: ' . $error);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
if (false === $address) {
    throw new RuntimeException('Could not inspect the loopback address.');
}

$cases = [
    '/error' => [500, 'legacy captured: synthetic downstream failure'],
    '/unavailable' => [500, 'legacy captured: synthetic upstream address'],
    '/client-error' => [404, 'legacy captured: synthetic client failure'],
    '/type-error' => [500, 'legacy captured: synthetic type mismatch'],
];
$readyDeadline = microtime(true) + 5;
$completed = false;
$result = runProcess([
    PHP_BINARY, '-n', '-d', 'display_errors=0', '-d', 'log_errors=0', '-d', 'memory_limit=32M',
    '-S', $address, __DIR__ . '/router.php',
], 15.0, 262144, static function () use ($address, $cases, $readyDeadline, &$completed): bool {
    $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
    if (!is_resource($connection)) {
        if (microtime(true) >= $readyDeadline) {
            throw new RuntimeException('Fixture server did not start within five seconds.');
        }

        return false;
    }
    fclose($connection);

    foreach ($cases as $route => [$expectedStatus, $expectedBody]) {
        $context = stream_context_create(['http' => [
            'ignore_errors' => true, 'timeout' => 2, 'follow_location' => 0,
            'max_redirects' => 0, 'header' => 'Connection: close',
        ]]);
        $stream = fopen('http://' . $address . $route, 'r', false, $context);
        if (false === $stream) {
            throw new RuntimeException('Fixture request failed: ' . $route);
        }

        try {
            $body = stream_get_contents($stream, 65537);
            $metadata = stream_get_meta_data($stream);
        } finally {
            fclose($stream);
        }
        $headers = $metadata['wrapper_data'] ?? [];
        if (!is_array($headers) || !isset($headers[0]) || !is_string($headers[0])) {
            throw new RuntimeException('Fixture response did not include HTTP headers.');
        }
        if (
            !is_string($body) || strlen($body) > 65536 || $metadata['timed_out']
            || false === strpos($headers[0], ' ' . $expectedStatus . ' ')
            || $body !== $expectedBody || !in_array('X-Fixture-Reports: 1', $headers, true)
            || !in_array('X-Fixture-Callbacks-Restored: yes', $headers, true)
        ) {
            throw new RuntimeException('Unexpected legacy response: ' . $route . ' ' . json_encode([$headers, $body]));
        }
        printf("PASS HTTP %-14s status=%d legacy capture, callbacks restored\n", $route, $expectedStatus);
    }
    $completed = true;

    return true;
}, ['ERROR_HANDLER_TEST_AUTOLOAD' => $autoload]);

if (!$completed || 'completed' !== $result['reason'] || !$result['reaped']) {
    throw new RuntimeException('Loopback fixture failed or exceeded a bound: ' . json_encode($result));
}
printf("PASS HTTP server terminated and reaped on PHP %s\n", PHP_VERSION);
