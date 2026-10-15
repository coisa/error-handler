<?php

declare(strict_types=1);

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

// No PHPUnit/application state is shared with the crash scenarios.
// Each child has a five-second deadline, a 16 MiB limit and a 256 KiB output cap.
$cases = [
    'warning' => [0, ['error', 'continued'], 'CoiSA\\ErrorHandler\\Exception\\ErrorException', E_USER_WARNING],
    'native-warning' => [0, ['error', 'continued'], 'CoiSA\\ErrorHandler\\Exception\\ErrorException', E_WARNING],
    'suppression' => [0, ['continued']],
    'exception' => [1, ['error'], RuntimeException::class],
    'exception-shutdown' => [1, ['error', 'shutdown'], RuntimeException::class],
    'nested-exception-shutdown' => [1, ['error', 'shutdown'], RuntimeException::class],
    'exception-shutdown-throws' => [1, ['error', 'error'], RuntimeException::class, null,
        [0 => ['message' => 'uncaught exception'], 1 => ['message' => 'shutdown failed']]],
    'exception-replaced-handler' => [1, ['error'], RuntimeException::class],
    'error' => [1, ['error'], Error::class],
    'type-error' => [1, ['error'], TypeError::class],
    'parse-error' => [1, ['error'], ParseError::class],
    'compile-fatal' => [255, ['error'], 'CoiSA\\ErrorHandler\\Exception\\ErrorException', E_COMPILE_ERROR],
    'nested-fatal' => [255, ['error'], 'CoiSA\\ErrorHandler\\Exception\\ErrorException', E_COMPILE_ERROR],
    'memory-limit' => [255, ['error'], 'CoiSA\\ErrorHandler\\Exception\\ErrorException', E_ERROR],
    'shutdown-once' => [0, ['shutdown']],
    'unregistered-shutdown' => [0, ['continued']],
    'restore' => [42, ['error', 'previous-error', 'previous-exception']],
    'out-of-order' => [0, ['rejected', 'error']],
    'reporter-throws' => [255, ['reporter-called']],
    'shutdown-reporter-throws' => [255, ['reporter-called']],
    'recursive-reporter' => [255, ['reporter-called']],
    'reporter-reset' => [0, ['reporter-called', 'first-failure', 'suppressed-retry', 'reporter-called'], null, null,
        [1 => ['message' => 'reporter failed'], 2 => ['message' => 'second throwable']]],
    'reporter-warning' => [0, ['reporter-called', 'continued']],
    'shutdown-throws' => [0, ['error'], RuntimeException::class],
    'aggregate' => [0, ['first', 'second']],
];
if (in_array('--with-psr', $argv, true)) {
    $cases['render-removable'] = [0, ['renderer-failure'], RuntimeException::class, null,
        [0 => ['message' => 'renderer failed', 'remainingBuffers' => 0]]];
    $cases['render-non-removable'] = [0, ['renderer-failure'], RuntimeException::class, null,
        [0 => ['message' => 'renderer failed', 'remainingBuffers' => 2]]];
    $cases['render-cleanup-throws'] = [0, ['renderer-failure'], RuntimeException::class, null,
        [0 => ['message' => 'renderer failed', 'remainingBuffers' => 0]]];
    $cases['render-cleanup-only-throws'] = [0, ['renderer-failure'], LogicException::class, null,
        [0 => ['message' => 'cleanup failed', 'remainingBuffers' => 0]]];
}
$failed = 0;
printf("PHP %s | %s %s | %d isolated scenarios\n", PHP_VERSION, PHP_OS_FAMILY, php_uname('m'), count($cases));
foreach ($cases as $name => $expected) {
    $process = proc_open([
        PHP_BINARY, '-n', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
        '-d', 'error_reporting=-1', '-d', 'memory_limit=16M',
        __DIR__ . '/scenario.php', $name,
    ], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Could not start child process');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $stdout = $stderr = '';
    $deadline = microtime(true) + 5;
    $bounded = true;
    do {
        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (microtime(true) > $deadline || strlen($stdout) + strlen($stderr) > 262144) {
            proc_terminate($process, 9);
            $bounded = false;
            break;
        }
        if ($status['running']) {
            usleep(1000);
        }
    } while ($status['running']);
    $stdout .= stream_get_contents($pipes[1]);
    $stderr .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed = proc_close($process);
    $exitCode = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
    $events = [];
    $validJson = true;
    foreach (array_filter(explode("\n", trim($stdout))) as $line) {
        $event = json_decode($line, true);
        if (!is_array($event) || !isset($event['event'])) {
            $validJson = false;
            break;
        }
        $events[] = $event;
    }
    $actualNames = array_column($events, 'event');
    $ok = $bounded && $validJson && $exitCode === $expected[0] && $actualNames === $expected[1];
    if (isset($expected[2])) {
        $ok = $ok && ($events[0]['class'] ?? null) === $expected[2];
    }
    if (isset($expected[3])) {
        $ok = $ok && ($events[0]['severity'] ?? null) === $expected[3];
    }
    foreach ($expected[4] ?? [] as $index => $fields) {
        foreach ($fields as $field => $value) {
            $ok = $ok && ($events[$index][$field] ?? null) === $value;
        }
    }
    $nativeDiagnostic = in_array($name, [
        'compile-fatal', 'nested-fatal', 'memory-limit', 'reporter-throws', 'shutdown-reporter-throws',
        'recursive-reporter', 'reporter-warning',
    ], true);
    $ok = $ok && ($nativeDiagnostic ? $stderr !== '' : $stderr === '');
    printf("%s %-27s exit=%d events=%d\n", $ok ? 'PASS' : 'FAIL', $name, $exitCode, count($events));
    if (!$ok) {
        ++$failed;
        printf(
            "  bounded=%s expected=%s actual=%s stderr=%s\n",
            $bounded ? 'yes' : 'no',
            json_encode($expected),
            json_encode(array_slice($events, 0, 4)),
            substr($stderr, 0, 600)
        );
    }
}
printf("%d passed; %d failed.\n", count($cases) - $failed, $failed);
exit($failed === 0 ? 0 : 1);
