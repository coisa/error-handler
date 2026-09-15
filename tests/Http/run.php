<?php

declare(strict_types=1);

// Reserve a loopback port; every fixture server and request has a bounded lifetime.
$socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($socket === false) {
    throw new RuntimeException($error);
}
$address = stream_socket_get_name($socket, false);
fclose($socket);
$process = proc_open([
    PHP_BINARY, '-d', 'display_errors=0', '-d', 'memory_limit=64M',
    '-S', $address, __DIR__ . '/router.php',
], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($process)) {
    throw new RuntimeException('Could not start fixture server');
}
fclose($pipes[0]);
stream_set_blocking($pipes[1], false);
stream_set_blocking($pipes[2], false);
try {
    $ready = false;
    $deadline = microtime(true) + 5;
    do {
        $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
        if (is_resource($connection)) {
            fclose($connection);
            $ready = true;
            break;
        }
        if (!proc_get_status($process)['running']) {
            break;
        }
        usleep(10000);
    } while (microtime(true) < $deadline);
    if (!$ready) {
        throw new RuntimeException('Fixture server did not start: ' . stream_get_contents($pipes[2]));
    }
    foreach (['/error' => 500, '/unavailable' => 503, '/type-error' => 500] as $route => $expected) {
        $context = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 2]]);
        $stream = fopen('http://' . $address . $route, 'r', false, $context);
        if ($stream === false) {
            throw new RuntimeException('Request failed: ' . $route);
        }
        $body = stream_get_contents($stream);
        $metadata = stream_get_meta_data($stream);
        fclose($stream);
        $status = $metadata['wrapper_data'][0];
        if (!str_contains($status, ' ' . $expected . ' ') || $body !== 'Internal Server Error') {
            throw new RuntimeException('Unexpected response: ' . $status . ' ' . $body);
        }
        printf("PASS HTTP %-14s status=%d body=redacted\n", $route, $expected);
    }
} finally {
    proc_terminate($process);
    $deadline = microtime(true) + 1;
    while (proc_get_status($process)['running'] && microtime(true) < $deadline) {
        usleep(10000);
    }
    if (proc_get_status($process)['running']) {
        proc_terminate($process, 9);
    }
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
}
