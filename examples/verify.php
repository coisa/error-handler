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

$cases = [
    ['direct.php', [], 0, "reported: Example failure\ncontinued\n"],
    ['handlers.php', [], 0, "callable, aggregate, conversion, suppression and shutdown adapters passed\n"],
    ['events.php', [], 0, "raw throwable, ErrorEvent, listener providers and PSR-3 logging passed\n"],
    ['container.php', [], 0, "legacy container, cached services, fresh factory and ConfigProvider passed\n"],
    ['process.php', ['warning'], 0, "reported: Process warning\ncontinued\n"],
    ['process.php', ['scope'], 0, "reported: Scoped warning\ncallbacks restored\n"],
    ['process.php', ['uncaught'], 0, "reported: Uncaught fixture\n"],
    ['process.php', ['shutdown'], 0, "leaving main\nshutdown callback\n"],
    ['process.php', ['fatal'], 255, null],
];

foreach (['capture', 'empty', 'zero', 'safe', 'success', 'renderer-failure', 'reporter-failure'] as $mode) {
    $cases[] = [
        'http.php',
        [$mode],
        0,
        'HTTP ' . $mode . ': response/reporting contract and callback restoration passed; buffer delta '
            . ('reporter-failure' === $mode ? '1' : '0') . "\n",
    ];
}

$boundedBefore = getenv('ERROR_HANDLER_BOUNDED_FIXTURE');
putenv('ERROR_HANDLER_BOUNDED_FIXTURE=1');

try {
    foreach ($cases as [$file, $arguments, $expectedExit, $expectedOutput]) {
        $command = array_merge([
            PHP_BINARY,
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
            __DIR__ . '/' . $file,
        ], $arguments);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the bounded example child.');
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $output = '';
        $errors = '';
        $deadline = microtime(true) + 5;

        do {
            $output .= stream_get_contents($pipes[1]);
            $errors .= stream_get_contents($pipes[2]);
            $state = proc_get_status($process);

            if (strlen($output) + strlen($errors) > 16384 || microtime(true) > $deadline) {
                proc_terminate($process, 9);

                throw new RuntimeException('The example exceeded its five-second or 16 KiB bound.');
            }

            if ($state['running']) {
                usleep(10000);
            }
        } while ($state['running']);

        $output .= stream_get_contents($pipes[1]);
        $errors .= stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $closed = proc_close($process);
        $exit = $state['exitcode'] >= 0 ? $state['exitcode'] : $closed;

        if ($exit !== $expectedExit || (null !== $expectedOutput && ($output !== $expectedOutput || '' !== $errors))) {
            throw new RuntimeException(
                $file . ' ' . implode(' ', $arguments) . ' failed: exit=' . $exit . "\n" . $output . $errors,
            );
        }

        if (null === $expectedOutput && (false === strpos($output, 'reported: Cannot redeclare') || 255 !== $exit)) {
            throw new RuntimeException('The fatal fixture did not report its native compile failure.');
        }

        echo $file . (' ' . implode(' ', $arguments)) . ': passed' . PHP_EOL;
    }
} finally {
    putenv(
        false === $boundedBefore ? 'ERROR_HANDLER_BOUNDED_FIXTURE' : 'ERROR_HANDLER_BOUNDED_FIXTURE=' . $boundedBefore,
    );
}

echo count($cases) . ' bounded example scenarios passed on PHP ' . PHP_VERSION . PHP_EOL;
