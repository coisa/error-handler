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

if (!isset($argv[1], $argv[2], $argv[3])) {
    throw new RuntimeException('Run buffer scenarios through tests/Fire/buffers.php.');
}
[$script, $case, $autoload, $source] = $argv;

require $autoload;

/**
 * Loads the selected library source before any Composer package copy.
 *
 * @param string $class the requested class name
 */
$loadSource = static function (string $class) use ($source): void {
    $prefix = 'CoiSA\ErrorHandler\\';
    if (0 === strncmp($class, $prefix, strlen($prefix))) {
        require $source . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
};
spl_autoload_register($loadSource, true, true);

if (in_array($case, ['cleanup-false', 'no-progress'], true)) {
    $GLOBALS['bufferFixtureMode'] = $case;
    $GLOBALS['bufferFixtureCleanupCalls'] = 0;

    require __DIR__ . '/buffer-functions.php';
}

$original = new RuntimeException('synthetic original failure');
$rendererFailure = new RuntimeException('synthetic renderer failure');
$secondary = new RuntimeException('synthetic callback failure');
$callbackCalls = 0;
$callerCalls = 0;
$handledSame = false;

/**
 * Records unexpected early invocation of a caller-owned output handler.
 *
 * @param string $buffer the caller-owned bytes
 *
 * @return string the unchanged caller bytes
 */
$caller = static function (string $buffer) use (&$callerCalls): string {
    ++$callerCalls;

    return $buffer;
};

/**
 * Throws once while allowing the child to finish its subsequent shutdown.
 *
 * @param string $buffer the nested output bytes
 *
 * @return string unchanged bytes after the first invocation
 *
 * @throws RuntimeException the synthetic first callback failure
 */
$throwing = static function (string $buffer) use (&$callbackCalls, $secondary): string {
    if (1 === ++$callbackCalls) {
        throw $secondary;
    }

    return $buffer;
};

if ('no-caller' !== $case) {
    ob_start();
    echo 'C0:';
    if ('protected-caller' === $case) {
        ob_start($caller, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
    } else {
        ob_start();
    }
    echo 'C1:';
}
$initialLevel = ob_get_level();

/**
 * Preserves bytes in a non-removable nested buffer.
 *
 * @param string $buffer the captured bytes
 *
 * @return string the same captured bytes
 */
$identity = static fn(string $buffer): string => $buffer;

/**
 * Exercises success, primary failure and output-handler failure paths.
 *
 * @param Throwable $throwable the original supplied failure
 *
 * @throws RuntimeException the original synthetic renderer failure
 */
$renderer = new CallableThrowableHandler(static function (Throwable $throwable) use (
    $case,
    $original,
    $rendererFailure,
    $throwing,
    $identity,
    &$handledSame
): void {
    $handledSame = $throwable === $original;

    switch ($case) {
        case 'content':
            echo "public\0bytes\n";

            return;

        case 'empty':
            return;

        case 'zero':
            echo '0';

            return;

        case 'balanced':
            echo 'outer';
            ob_start();
            echo 'inner';
            ob_end_flush();

            return;

        case 'unbalanced':
            echo 'outer';
            ob_start();
            echo 'inner';

            return;

        case 'nested-failure':
            echo 'outer';
            ob_start();
            echo 'middle';
            ob_start();
            echo 'inner';

            throw $rendererFailure;

        case 'closed-capture':
            ob_end_clean();

            throw $rendererFailure;

        case 'protected-top':
            ob_start($identity, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);

            throw $rendererFailure;

        case 'cleanup-throws':
            ob_start($throwing);

            throw $rendererFailure;

        case 'capture-throws':
            ob_start($throwing);

            return;
    }
    echo 'discard';

    throw $rendererFailure;
});

$factory = new ThrowableStreamFactory(new Psr17Factory(), $renderer);
$body = null;
$caught = null;

try {
    $body = (string) $factory->createStreamFromThrowable($original);
} catch (Throwable $failure) {
    $caught = $failure;
}
$success = in_array($case, ['content', 'empty', 'zero', 'balanced', 'unbalanced'], true);
$expectedBody = ['content' => "public\0bytes\n", 'balanced' => 'outerinner', 'unbalanced' => 'inner'];
$expectedPrimary = 'capture-throws' === $case ? $secondary : $rendererFailure;
fwrite(STDERR, json_encode([
    'case' => $case,
    'source' => (new ReflectionClass(ThrowableStreamFactory::class))->getFileName(),
    'handledSame' => $handledSame,
    'primarySame' => $success ? null === $caught : $caught === $expectedPrimary,
    'remaining' => ob_get_level() - $initialLevel,
    'callbackCalls' => $callbackCalls,
    'callerCallbackCalls' => $callerCalls,
    'cleanupCalls' => $GLOBALS['bufferFixtureCleanupCalls'] ?? 0,
    'bodyMatches' => $success ? $body === ($expectedBody[$case] ?? (string) $original) : null === $body,
    'body' => null === $body ? null : base64_encode($body),
], JSON_THROW_ON_ERROR) . "\n");
