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

require __DIR__ . '/../Support/check.php';

$cases = [
    'warning' => [0, ['error', 'continued'], 'CoiSA\ErrorHandler\Exception\ErrorException', E_USER_WARNING],
    'native-warning' => [0, ['error', 'continued'], 'CoiSA\ErrorHandler\Exception\ErrorException', E_WARNING],
    'suppression' => [0, ['continued']],
    'exception' => [0, ['error'], RuntimeException::class],
    'error' => [0, ['error'], Error::class],
    'type-error' => [0, ['error'], TypeError::class],
    'parse-error' => [0, ['error'], ParseError::class],
    'compile-fatal' => [255, ['error'], 'CoiSA\ErrorHandler\Exception\ErrorException', E_COMPILE_ERROR],
    'nested-fatal' => [255, ['error', 'error'], 'CoiSA\ErrorHandler\Exception\ErrorException', E_COMPILE_ERROR],
    'memory-limit' => [255, ['error'], 'CoiSA\ErrorHandler\Exception\ErrorException', E_ERROR],
    'shutdown-repeated' => [0, ['shutdown', 'shutdown', 'shutdown', 'shutdown']],
    'unregistered-shutdown' => [0, ['continued']],
    'restore' => [42, ['error', 'previous-error', 'previous-exception']],
    'reporter-throws' => [255, ['reporter-called', 'reporter-called', 'reporter-called']],
    'shutdown-throws' => [0, ['error'], RuntimeException::class],
    'aggregate' => [0, ['first', 'second'], null, null, [0 => ['same' => true], 1 => ['same' => true]]],
];
$limitations = [
    'exception', 'error', 'type-error', 'parse-error', 'nested-fatal', 'shutdown-repeated', 'reporter-throws',
];
$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
$arguments = $argv ?? [];
foreach ($arguments as $argument) {
    if (0 === strpos($argument, '--autoload=')) {
        $autoload = substr($argument, strlen('--autoload='));
    }
}

if (in_array('--with-psr', $arguments, true)) {
    if (!is_file($autoload)) {
        throw new RuntimeException('Install PSR dependencies or use --autoload=/absolute/path/vendor/autoload.php.');
    }
    $cases['render-content'] = [0, ['rendered'], null, null,
        [0 => ['captured' => true, 'diagnosticFallback' => false, 'remainingBuffers' => 0]]];
    $cases['render-empty'] = [0, ['rendered'], null, null,
        [0 => ['captured' => false, 'diagnosticFallback' => true, 'remainingBuffers' => 0]]];
    $cases['render-zero'] = $cases['render-empty'];
    $cases['render-throws'] = [0, ['renderer-failure'], RuntimeException::class, null,
        [0 => ['message' => 'synthetic renderer failure', 'remainingBuffers' => 1]]];
    $cases['render-non-removable'] = [0, ['renderer-failure'], RuntimeException::class, null,
        [0 => ['message' => 'synthetic renderer failure', 'remainingBuffers' => 2]]];
    $limitations = array_merge($limitations, ['render-empty', 'render-zero', 'render-throws', 'render-non-removable']);
}

$failed = 0;
printf(
    "PHP %s | %s %s | %d isolated 2.x characterizations\n",
    PHP_VERSION,
    PHP_OS_FAMILY,
    php_uname('m'),
    count($cases),
);
foreach ($cases as $name => $expected) {
    $result = runProcess([
        PHP_BINARY, '-n', '-d', 'display_errors=stderr', '-d', 'log_errors=0',
        '-d', 'error_reporting=-1', '-d', 'memory_limit=16M',
        __DIR__ . '/scenario.php', $name, $autoload,
    ]);

    $events = [];
    $validJson = true;
    foreach (array_filter(explode("\n", trim($result['stdout']))) as $line) {
        $event = json_decode($line, true);
        if (!is_array($event) || !isset($event['event'])) {
            $validJson = false;

            break;
        }
        $events[] = $event;
    }

    $ok = 'exit' === $result['reason'] && $result['reaped'] && $validJson
        && $result['exitCode'] === $expected[0] && array_column($events, 'event') === $expected[1];
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
    $native = in_array($name, ['compile-fatal', 'nested-fatal', 'memory-limit', 'reporter-throws'], true);
    $ok = $ok && ($native ? '' !== $result['stderr'] : '' === $result['stderr']);
    printf(
        "%s %-23s exit=%d events=%d%s\n",
        $ok ? 'PASS' : 'FAIL',
        $name,
        $result['exitCode'],
        count($events),
        in_array($name, $limitations, true) ? ' [known limitation]' : '',
    );
    if (!$ok) {
        ++$failed;
        printf(
            "  reason=%s expected=%s actual=%s stderr=%s\n",
            $result['reason'],
            json_encode($expected),
            json_encode($events),
            substr($result['stderr'], 0, 600),
        );
    }
}

printf("%d characterized; %d mismatches.\n", count($cases) - $failed, $failed);

exit(0 === $failed ? 0 : 1);
