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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableShutdownHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require __DIR__ . '/bootstrap.php';

$reporter = new CallableThrowableHandler(static function (Throwable $error): void {
    fireEvent('error', [
        'class' => get_class($error),
        'severity' => $error instanceof ErrorException ? $error->getSeverity() : null,
    ]);
});
$handler = new ErrorHandler($reporter);
$scenario = $argv[1] ?? '';

switch ($scenario) {
    case 'warning':
        $handler->register();
        trigger_error('synthetic warning', E_USER_WARNING);
        fireEvent('continued');

        break;

    case 'native-warning':
        $handler->register();
        fopen(__DIR__ . '/does-not-exist.fixture', 'r');
        fireEvent('continued');

        break;

    case 'suppression':
        $handler->register();
        error_reporting(E_ALL & ~E_USER_WARNING);
        trigger_error('masked warning', E_USER_WARNING);
        error_reporting(E_ALL);
        @trigger_error('suppressed warning', E_USER_WARNING);
        @fopen(__DIR__ . '/does-not-exist.fixture', 'r');
        fireEvent('continued');

        break;

    case 'exception':
        $handler->register();

        throw new RuntimeException('synthetic uncaught exception');

    case 'error':
        $handler->register();
        // @phpstan-ignore function.notFound (This bounded fixture deliberately produces an engine Error.)
        nonexistent_characterization_function();

        break;

    case 'type-error':
        $handler->register();
        // @phpstan-ignore argument.type (This bounded fixture deliberately produces an engine TypeError.)
        (static fn(int $value): int => $value)('not an integer');

        break;

    case 'parse-error':
        $handler->register();

        require __DIR__ . '/parse-error.inc';

        break;

    case 'compile-fatal':
    case 'nested-fatal':
        $handler->register();
        if ('nested-fatal' === $scenario) {
            (new ErrorHandler($reporter))->register();
        }

        require __DIR__ . '/compile-fatal.inc';

        break;

    case 'memory-limit':
        $handler->register();
        $allocation = str_repeat('x', 32 * 1024 * 1024);
        fireEvent('unexpected-allocation', ['length' => strlen($allocation)]);

        break;

    case 'shutdown-repeated':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            fireEvent('shutdown');
        }));
        for ($index = 0; $index < 3; ++$index) {
            $handler->register();
            $handler->register();
            $handler->unregister();
        }
        $handler->register();

        break;

    case 'unregistered-shutdown':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            fireEvent('unexpected-shutdown');
        }));
        $handler->register();
        $handler->unregister();
        $handler->unregister();
        fireEvent('continued');

        break;

    case 'restore':
        set_error_handler(static function (): bool {
            fireEvent('previous-error');

            return true;
        }, E_USER_NOTICE);
        set_exception_handler(static function (Throwable $error): void {
            fireEvent('previous-exception');

            exit(42);
        });
        $inner = new ErrorHandler($reporter);
        $handler->register();
        $inner->register();
        $inner->unregister();
        trigger_error('outer active', E_USER_WARNING);
        $handler->unregister();
        @trigger_error('outside previous mask', E_USER_WARNING);
        trigger_error('previous active', E_USER_NOTICE);

        throw new RuntimeException('previous exception active');

    case 'reporter-throws':
        $handler = new ErrorHandler(new CallableThrowableHandler(static function (): void {
            fireEvent('reporter-called');

            throw new RuntimeException('synthetic reporter failure');
        }));
        $handler->register();

        throw new RuntimeException('original failure');

    case 'shutdown-throws':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(static function (): void {
            throw new RuntimeException('synthetic shutdown failure');
        }));
        $handler->register();

        break;

    case 'aggregate':
        $original = new RuntimeException('synthetic chain');
        $aggregate = new ThrowableHandlerAggregate(
            new CallableThrowableHandler(static function (Throwable $error) use ($original): void {
                fireEvent('first', ['same' => $original === $error]);
            }),
            new CallableThrowableHandler(static function (Throwable $error) use ($original): void {
                fireEvent('second', ['same' => $original === $error]);
            }),
        );
        (new ErrorHandler($aggregate))->handleThrowable($original);

        break;

    case 'render-content':
    case 'render-empty':
    case 'render-zero':
    case 'render-throws':
    case 'render-non-removable':
        require $argv[2] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';
        $original = new RuntimeException('synthetic render failure');
        $renderer = new CallableThrowableHandler(static function (Throwable $error) use ($scenario, $original): void {
            if ($error !== $original) {
                throw new LogicException('Original throwable identity lost.');
            }
            if ('render-content' === $scenario) {
                echo 'legacy captured content';
            } elseif ('render-zero' === $scenario) {
                echo '0';
            } elseif ('render-throws' === $scenario || 'render-non-removable' === $scenario) {
                if ('render-non-removable' === $scenario) {
                    /**
                     * Preserves bytes while making this nested fixture buffer non-removable.
                     *
                     * @param string $buffer the captured fixture output
                     *
                     * @return string the unchanged captured output
                     */
                    $passthrough = static fn(string $buffer): string => $buffer;
                    ob_start($passthrough, 0, PHP_OUTPUT_HANDLER_CLEANABLE | PHP_OUTPUT_HANDLER_FLUSHABLE);
                }

                throw new RuntimeException('synthetic renderer failure');
            }
        });
        $initialLevel = ob_get_level();
        $factory = new ThrowableStreamFactory(new Psr17Factory(), $renderer);

        try {
            $body = (string) $factory->createStreamFromThrowable($original);
            fireEvent('rendered', [
                'captured' => 'legacy captured content' === $body,
                'diagnosticFallback' => 0 === strpos($body, 'RuntimeException: synthetic render failure'),
                'remainingBuffers' => ob_get_level() - $initialLevel,
            ]);
        } catch (Throwable $failure) {
            $remaining = ob_get_level() - $initialLevel;
            if ('render-throws' === $scenario) {
                while (ob_get_level() > $initialLevel) {
                    ob_end_clean();
                }
            }
            fireEvent('renderer-failure', [
                'class' => get_class($failure),
                'message' => $failure->getMessage(),
                'remainingBuffers' => $remaining,
            ]);
        }

        break;

    default:
        throw new InvalidArgumentException('Unknown fixture: ' . $scenario);
}
