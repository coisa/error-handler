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

use Symfony\Component\Process\Process;

require getenv('ERROR_HANDLER_AUTOLOAD') ?: dirname(__DIR__) . '/vendor/autoload.php';

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
            . "0\n",
    ];
}

$inherited = getenv();
if (!is_array($inherited)) {
    throw new RuntimeException('Could not inspect inherited environment variable names.');
}
$environment = array_fill_keys(array_keys(array_merge($_SERVER, $_ENV, $inherited)), false);
$environment['ERROR_HANDLER_BOUNDED_FIXTURE'] = '1';
$autoload = getenv('ERROR_HANDLER_AUTOLOAD');

if (false !== $autoload) {
    $environment['ERROR_HANDLER_AUTOLOAD'] = $autoload;
}

foreach ($cases as [$file, $arguments, $expectedExit, $expectedOutput]) {
    $command = array_merge([
        PHP_BINARY,
        '-n',
        '-d', 'memory_limit=64M',
        '-d', 'display_errors=stderr',
        '-d', 'log_errors=0',
        __DIR__ . '/' . $file,
    ], $arguments);
    $process = new Process($command, null, $environment, null, 5.0);
    $bytes = 0;
    $limited = false;

    try {
        $process->run(
            /**
             * Interrupts excessive example output without relaunching an error during cleanup.
             *
             * @param string $type  the standard pipe identifier
             * @param string $chunk the new example bytes
             *
             * @throws LengthException when combined output exceeds16KiB
             */
            static function (string $type, string $chunk) use (&$bytes, &$limited): void {
                $bytes += strlen($chunk);
                if ($bytes > 16384 && !$limited) {
                    $limited = true;

                    throw new LengthException('Example output exceeded16KiB.');
                }
            },
        );
    } finally {
        $process->stop(0);
    }

    $exit = $process->getExitCode();
    $output = $process->getOutput();
    $errors = $process->getErrorOutput();

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

echo count($cases) . ' bounded example scenarios passed on PHP ' . PHP_VERSION . PHP_EOL;
