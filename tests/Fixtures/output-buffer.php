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

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$case = $argv[1] ?? '';
$original = new RuntimeException('incoming event');
$primary = new RuntimeException('reporter failure');
$secondary = new RuntimeException('output callback failure');
$callbacks = 0;
$callerCallbacks = 0;
$received = null;

/**
 * Records premature invocation while preserving caller-owned bytes.
 *
 * @param string $buffer the caller-owned bytes
 *
 * @return string the same caller bytes
 */
$caller = static function (string $buffer) use (&$callerCallbacks): string {
    ++$callerCallbacks;

    return $buffer;
};
ob_start();
echo 'C0:';
ob_start($caller, 0, 'protected-caller' === $case
    ? PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE
    : PHP_OUTPUT_HANDLER_STDFLAGS);
echo 'C1:';
$entry = ob_get_level();

/**
 * Opens a hazardous scope only in this externally bounded child.
 *
 * @param Throwable $event the original event received by the reporter
 *
 * @throws RuntimeException the configured primary reporter failure
 */
$reporter = new CallableThrowableHandler(static function (Throwable $event) use (
    $case,
    $primary,
    $secondary,
    &$received,
    &$callbacks
): void {
    $received = $event;
    if ('protected-top' === $case) {
        /**
         * Preserves protected bytes without permitting removal of this scope.
         *
         * @param string $buffer the protected bytes
         *
         * @return string the same protected bytes
         */
        $identity = static fn(string $buffer): string => $buffer;
        ob_start($identity, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
    } elseif (in_array($case, ['capture-throws', 'terminates'], true)) {
        ob_start(
            /**
             * Fails once or terminates during real native output cleanup.
             *
             * @param string $buffer the output being discarded
             *
             * @return string unchanged output on a later shutdown invocation
             *
             * @throws RuntimeException the distinct first output-handler failure
             */
            static function (string $buffer) use ($case, $secondary, &$callbacks): string {
                ++$callbacks;
                if ('terminates' === $case) {
                    fwrite(STDERR, "terminating callback\n");

                    exit(23);
                }
                if (1 === $callbacks) {
                    throw $secondary;
                }

                return $buffer;
            },
        );
    }
    if ('capture-throws' === $case) {
        return;
    }

    throw $primary;
});
$caught = null;

try {
    (new ThrowableStreamFactory(new Psr17Factory(), $reporter))->createStreamFromThrowable($original);
} catch (Throwable $failure) {
    $caught = $failure;
}
if ('terminates' === $case) {
    fwrite(STDERR, "unexpected caller continuation\n");
}
fwrite(STDERR, json_encode([
    'originalSame' => $received === $original,
    'primarySame' => $caught === ('capture-throws' === $case ? $secondary : $primary),
    'remaining' => ob_get_level() - $entry,
    'callbacks' => $callbacks,
    'callerCallbacks' => $callerCallbacks,
], JSON_THROW_ON_ERROR) . "\n");
