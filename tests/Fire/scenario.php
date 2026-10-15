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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;

require __DIR__ . '/bootstrap.php';

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    event('error', [
        'class' => get_class($error),
        'message' => $error->getMessage(),
        'severity' => $error instanceof ErrorException ? $error->getSeverity() : null,
    ]);
});
$handler = new ErrorHandler($reporter);
$scenario = $argv[1] ?? '';

switch ($scenario) {
    case 'warning':
        $handler->register();
        trigger_error('visible warning', E_USER_WARNING);
        event('continued');
        break;
    case 'native-warning':
        $handler->register();
        fopen(__DIR__ . '/does-not-exist.fixture', 'r');
        event('continued');
        break;
    case 'suppression':
        $handler->register();
        error_reporting(E_ALL & ~E_USER_WARNING);
        trigger_error('masked warning', E_USER_WARNING);
        error_reporting(E_ALL);
        @trigger_error('suppressed warning', E_USER_WARNING);
        @fopen(__DIR__ . '/does-not-exist.fixture', 'r');
        event('continued');
        break;
    case 'exception':
        $handler->register();
        throw new RuntimeException('uncaught exception');
    case 'exception-replaced-handler':
        $handler = new ErrorHandler(
            new CallableThrowableHandler(static function (Throwable $failure) use ($reporter): void {
                $reporter->handleThrowable($failure);
                set_exception_handler(static function (): void {
                    event('unexpected-exception');
                });
            }),
            null,
            new CallableShutdownHandler(static function (): void {
                event('unexpected-shutdown');
            })
        );
        $handler->register();
        throw new RuntimeException('uncaught exception');
    case 'exception-shutdown':
    case 'nested-exception-shutdown':
    case 'exception-shutdown-throws':
        if ($scenario === 'nested-exception-shutdown') {
            (new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
                event('unexpected-outer-shutdown');
            })))->register();
        }
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(
            static function () use ($scenario): void {
                if ($scenario === 'exception-shutdown-throws') {
                    throw new RuntimeException('shutdown failed');
                }
                event('shutdown');
            }
        ));
        $handler->register();
        throw new RuntimeException('uncaught exception');
    case 'error':
        $handler->register();
        error_handler_nonexistent_function();
        break;
    case 'type-error':
        $handler->register();
        strlen([]);
        break;
    case 'parse-error':
        $handler->register();
        require __DIR__ . '/parse-error.inc';
        break;
    case 'compile-fatal':
    case 'nested-fatal':
        $handler->register();
        if ($scenario === 'nested-fatal') {
            (new ErrorHandler($reporter))->register();
        }
        require __DIR__ . '/compile-fatal.inc';
        break;
    case 'memory-limit':
        $handler->register();
        // Allocation fails only inside this child, capped at 16 MiB by the runner.
        str_repeat('x', 32 * 1024 * 1024);
        break;
    case 'shutdown-once':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            event('shutdown');
        }));
        for ($i = 0; $i < 25; ++$i) {
            $handler->register();
            $handler->register();
            $handler->unregister();
        }
        $handler->register();
        break;
    case 'unregistered-shutdown':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            event('unexpected-shutdown');
        }));
        $handler->register();
        $handler->unregister();
        $handler->unregister();
        event('continued');
        break;
    case 'restore':
        $previousError = static function (): bool {
            event('previous-error');
            return true;
        };
        $previousException = static function (Throwable $error): void {
            event('previous-exception');
            exit(42);
        };
        set_error_handler($previousError, E_USER_NOTICE);
        set_exception_handler($previousException);
        $inner = new ErrorHandler($reporter);
        $handler->register();
        $inner->register();
        $inner->unregister();
        trigger_error('outer active', E_USER_WARNING);
        $handler->unregister();
        @trigger_error('outside previous mask', E_USER_WARNING);
        trigger_error('previous active', E_USER_NOTICE);
        throw new RuntimeException('previous exception active');
    case 'out-of-order':
        $inner = new ErrorHandler($reporter);
        $handler->register();
        $inner->register();
        try {
            $handler->unregister();
        } catch (LogicException $error) {
            event('rejected');
        }
        trigger_error('inner still active', E_USER_WARNING);
        $inner->unregister();
        $handler->unregister();
        break;
    case 'reporter-throws':
    case 'shutdown-reporter-throws':
        $handler = new ErrorHandler(new CallableThrowableHandler(static function (): void {
            event('reporter-called');
            throw new RuntimeException('reporter failed');
        }));
        $handler->register();
        if ($scenario === 'shutdown-reporter-throws') {
            require __DIR__ . '/compile-fatal.inc';
        }
        throw new RuntimeException('original failure');
    case 'recursive-reporter':
        $handler = new ErrorHandler(new CallableThrowableHandler(
            static function (Throwable $error) use (&$handler): void {
                event('reporter-called');
                $handler->handleThrowable($error);
            }
        ));
        $handler->register();
        throw new RuntimeException('original failure');
    case 'reporter-reset':
        $calls = 0;
        $handler = new ErrorHandler(new CallableThrowableHandler(static function () use (&$calls): void {
            event('reporter-called');
            if (++$calls === 1) {
                throw new RuntimeException('reporter failed');
            }
        }));
        $handler->register();
        try {
            $handler->handleThrowable(new RuntimeException('first throwable'));
        } catch (RuntimeException $failure) {
            event('first-failure', ['message' => $failure->getMessage()]);
        }
        try {
            $handler->handleThrowable(new RuntimeException('second throwable'));
        } catch (RuntimeException $failure) {
            event('suppressed-retry', ['message' => $failure->getMessage()]);
        }
        $handler->unregister();
        $handler->register();
        $handler->handleThrowable(new RuntimeException('fresh lifecycle'));
        $handler->unregister();
        break;
    case 'reporter-warning':
        $handler = new ErrorHandler(new CallableThrowableHandler(static function (): void {
            event('reporter-called');
            trigger_error('reporter warning', E_USER_WARNING);
        }));
        $handler->register();
        trigger_error('original warning', E_USER_WARNING);
        event('continued');
        break;
    case 'shutdown-throws':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            throw new RuntimeException('shutdown failed');
        }));
        $handler->register();
        break;
    case 'aggregate':
        $aggregate = new ThrowableHandlerAggregate(
            new CallableThrowableHandler(static function (Throwable $error): void {
                event('first');
            }),
            new CallableThrowableHandler(static function (Throwable $error): void {
                event('second');
            })
        );
        (new ErrorHandler($aggregate))->handleThrowable(new RuntimeException('chain'));
        break;
    case 'render-removable':
    case 'render-non-removable':
    case 'render-cleanup-throws':
    case 'render-cleanup-only-throws':
        require dirname(__DIR__, 2) . '/vendor/autoload.php';
        $flags = PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE;
        if ($scenario !== 'render-non-removable') {
            $flags |= PHP_OUTPUT_HANDLER_REMOVABLE;
        }
        $renderer = new CallableThrowableHandler(static function () use ($flags, $scenario): void {
            $callback = str_starts_with($scenario, 'render-cleanup-') ? static function (): never {
                throw new LogicException('cleanup failed');
            } : null;
            ob_start($callback, 0, $flags);
            if ($scenario !== 'render-cleanup-only-throws') {
                throw new RuntimeException('renderer failed');
            }
        });
        $factory = new CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory(
            new Nyholm\Psr7\Factory\Psr17Factory(),
            $renderer
        );
        $initialLevel = ob_get_level();
        try {
            $factory->createStreamFromThrowable(new RuntimeException('original'));
        } catch (Throwable $failure) {
            // Bypass the deliberately retained non-removable buffer.
            fwrite(STDOUT, json_encode([
                'event' => 'renderer-failure',
                'class' => get_class($failure),
                'message' => $failure->getMessage(),
                'remainingBuffers' => ob_get_level() - $initialLevel,
            ], JSON_THROW_ON_ERROR) . "\n");
        }
        break;
    default:
        throw new InvalidArgumentException('Unknown scenario: ' . $scenario);
}
