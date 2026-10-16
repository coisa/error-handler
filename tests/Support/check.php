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

require __DIR__ . '/process.php';

$cases = [
    'normal exit and both pipes' => ["fwrite(STDOUT, 'out'); fwrite(STDERR, 'err'); exit(7);", 1.0, 1024, 'exit', 7],
    'timeout terminates and reaps child' => ['usleep(2000000);', 0.05, 1024, 'timeout', null],
    'output cap reaps child' => ["while (true) { echo str_repeat('x', 8192); }", 1.0, 1024, 'output-limit', null],
];

foreach ($cases as $name => [$code, $timeout, $limit, $reason, $exit]) {
    $result = runProcess([PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-r', $code], $timeout, $limit);
    if (
        $result['reason'] !== $reason || !$result['reaped'] || $result['elapsed'] > 3
        || strlen($result['stdout']) + strlen($result['stderr']) > $limit + 1
        || (null !== $exit && $result['exitCode'] !== $exit)
        || ('exit' === $reason && ['out', 'err'] !== [$result['stdout'], $result['stderr']])
    ) {
        throw new RuntimeException('Harness self-check failed: ' . $name . ' ' . json_encode($result));
    }

    printf("PASS harness %s\n", $name);
}

foreach (['Fire', 'Http'] as $entrypoint) {
    $path = dirname(__DIR__, 2) . '/tests/' . $entrypoint . '/run.php';
    $code = 'unset($argv); require ' . var_export($path, true) . ';';
    $result = runProcess([
        PHP_BINARY, '-n', '-d', 'memory_limit=16M', '-d', 'display_errors=stderr', '-d', 'log_errors=0', '-r', $code,
    ], 1.0, 16384);
    $message = 'Fixture arguments are unavailable; use PHP CLI with register_argc_argv enabled.';
    if (
        'exit' !== $result['reason'] || 255 !== $result['exitCode'] || !$result['reaped']
        || '' !== $result['stdout'] || false === strpos($result['stderr'], $message)
    ) {
        throw new RuntimeException('Missing-arguments guard failed: ' . $entrypoint . ' ' . json_encode($result));
    }
    printf("PASS harness missing arguments fail before %s fixture work\n", $entrypoint);
}
