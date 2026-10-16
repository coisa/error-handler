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

if (!isset($argv)) {
    throw new RuntimeException('Fixture arguments are unavailable; use PHP CLI with register_argc_argv enabled.');
}
$arguments = $argv;

require __DIR__ . '/../Support/process.php';

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
$source = dirname(__DIR__, 2) . '/src';
foreach ($arguments as $argument) {
    if (0 === strpos($argument, '--autoload=')) {
        $autoload = substr($argument, strlen('--autoload='));
    } elseif (0 === strpos($argument, '--source=')) {
        $source = substr($argument, strlen('--source='));
    }
}
if (!is_file($autoload) || !is_file($source . '/Http/Message/ThrowableStreamFactory.php')) {
    throw new RuntimeException('Supply a compatible Composer autoload file and library source directory.');
}

$cases = [
    'content', 'empty', 'zero', 'balanced', 'unbalanced', 'failure', 'nested-failure',
    'closed-capture', 'protected-caller', 'protected-top', 'cleanup-throws', 'capture-throws',
    'no-caller', 'cleanup-false', 'no-progress',
];
$remaining = ['unbalanced' => 1, 'protected-top' => 2, 'cleanup-throws' => 1, 'cleanup-false' => 1, 'no-progress' => 1];
$observations = [];
$failed = 0;
$json = in_array('--json', $arguments, true);
foreach ($cases as $case) {
    $result = runProcess([
        PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-d', 'display_errors=stderr',
        '-d', 'log_errors=0', '-d', 'error_reporting=-1',
        __DIR__ . '/buffer-scenario.php', $case, $autoload, $source,
    ], 2.0, 65536);
    $event = json_decode(trim($result['stderr']), true);
    $expectedOutput = 'no-caller' === $case ? '' : 'C0:C1:';
    if ('unbalanced' === $case) {
        $expectedOutput .= 'outer';
    } elseif (in_array($case, ['cleanup-false', 'no-progress'], true)) {
        $expectedOutput .= 'discard';
    }
    $ok = 'exit' === $result['reason'] && $result['reaped'] && 0 === $result['exitCode']
        && $expectedOutput === $result['stdout'] && is_array($event)
        && true === ($event['handledSame'] ?? null) && true === ($event['primarySame'] ?? null)
        && true === ($event['bodyMatches'] ?? null) && 0 === ($event['callerCallbackCalls'] ?? null)
        && ($remaining[$case] ?? 0) === ($event['remaining'] ?? null)
        && (in_array($case, ['cleanup-throws', 'capture-throws'], true) ? 1 : 0) === ($event['callbackCalls'] ?? null)
        && (in_array($case, ['cleanup-false', 'no-progress'], true) ? 1 : 0) === ($event['cleanupCalls'] ?? null)
        && realpath($source . '/Http/Message/ThrowableStreamFactory.php') === ($event['source'] ?? null);
    $observations[$case] = ['ok' => $ok, 'result' => $result, 'event' => $event];
    if (!$ok) {
        ++$failed;
    }
    if (!$json) {
        printf("%s %-18s reason=%s\n", $ok ? 'PASS' : 'FAIL', $case, $result['reason']);
        if (!$ok) {
            printf("  %s\n", json_encode($observations[$case], JSON_THROW_ON_ERROR));
        }
    }
}
if ($json) {
    echo json_encode($observations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
} else {
    printf("PHP %s: %d buffer regressions; %d mismatches.\n", PHP_VERSION, count($cases), $failed);
}

exit(0 === $failed ? 0 : 1);
