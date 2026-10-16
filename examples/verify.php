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
    [
        'cli.php', [], 1, '',
        "CoiSA\\ErrorHandler\\Exception\\ErrorException: Example warning; execution continues.\n"
            . "RuntimeException: Example uncaught exception; exit status is 1.\n",
    ],
    ['fast-forward.php', [], 0, "Reported: FastForward provider warning\n", ''],
    ['reporters.php', [], 0, "first: Synthetic failure\nsecond: Synthetic failure\n", ''],
    [
        'event-dispatcher.php', [], 0,
        "throwable: Synthetic warning\nevent: Synthetic warning\nPrivate log records: 1\n", '',
    ],
    ['http-middleware.php', [], 0, "HTTP 503 | Internal Server Error\nReported: 2\n", ''],
    [
        'http-rendering.php', [], 0,
        "HTTP 503 | application/json\n{\"error\":\"Service unavailable\"}\nPrivate reports: 1\n", '',
    ],
    ['custom-handlers.php', [], 0, "PHP errors: 1 | Shutdown notifications: 1\n", ''],
    ['scopes.php', [], 0, "Fresh handlers: yes | Fresh middleware: yes\nHTTP 500 | Reports: 2\n", ''],
    ['output-capture.php', [], 0, "Captured body: Public failure page\n", ''],
    ['logging.php', [], 0, "direct PSR-3 logging, exact messages and original context passed\n", ''],
    ['filtering.php', [], 0, "explicit filtering, severity selection and original-object delivery passed\n", ''],
    ['best-effort.php', [], 0, "best-effort order, original cause and all reporter failures passed\n", ''],
    ['php-errors.php', [], 0, "callable PHP errors: four arguments, explicit mask policy and conversion passed\n", ''],
];

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

foreach ($cases as [$file, $arguments, $expectedExit, $expectedOutput, $expectedErrors]) {
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

    if ($exit !== $expectedExit || $output !== $expectedOutput || $errors !== $expectedErrors) {
        throw new RuntimeException(
            $file . ' ' . implode(' ', $arguments) . ' failed: exit=' . $exit . "\n" . $output . $errors,
        );
    }


    echo $file . (' ' . implode(' ', $arguments)) . ': passed' . PHP_EOL;
}

echo count($cases) . ' bounded example scenarios passed on PHP ' . PHP_VERSION . PHP_EOL;
