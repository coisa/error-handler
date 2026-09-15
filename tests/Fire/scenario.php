<?php

declare(strict_types=1);

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
    default:
        throw new InvalidArgumentException('Unknown scenario: ' . $scenario);
}
