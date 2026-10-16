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

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$case = $argv[1] ?? '';
$reporter = new CallableThrowableHandler(
    /**
     * Records actual events delivered by native exception/shutdown callbacks.
     *
     * @param Throwable $event the event supplied by the real handler
     */
    static function (Throwable $event): void {
        echo json_encode([
            'event' => 'error', 'class' => get_class($event),
            'severity' => $event instanceof ErrorException ? $event->getSeverity() : null,
        ], JSON_THROW_ON_ERROR) . "\n";
    },
);
$handler = new ErrorHandler($reporter);

switch ($case) {
    case 'exception':
        $handler->register();

        throw new RuntimeException('synthetic uncaught exception');

    case 'error':
        $handler->register();
        // @phpstan-ignore function.notFound (An owned bounded child verifies the native Error path.)
        nonexistent_characterization_function();

        break;

    case 'type-error':
        $handler->register();
        // @phpstan-ignore argument.type (An owned bounded child verifies the native TypeError path.)
        (static fn(int $value): int => $value)('not an integer');

        break;

    case 'parse-error':
        $handler->register();

        require __DIR__ . '/parse-error.inc';

        break;

    case 'compile-fatal':
    case 'nested-fatal':
        $handler->register();
        if ('nested-fatal' === $case) {
            (new ErrorHandler($reporter))->register();
        }

        require __DIR__ . '/compile-fatal.inc';

        break;

    case 'memory-limit':
        $handler->register();
        $allocation = str_repeat('x', 32 * 1024 * 1024);

        throw new LogicException('The child did not exhaust its 16 MiB limit: ' . strlen($allocation));

    case 'shutdown-repeated':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(
            /** Records each actual invocation retained in PHP's shutdown queue. */
            static function (): void {
                echo "{\"event\":\"shutdown\"}\n";
            },
        ));
        for ($index = 0; $index < 3; ++$index) {
            $handler->register();
            $handler->register();
            $handler->unregister();
        }
        $handler->register();

        break;

    case 'restore':
        set_error_handler(
            /**
             * Records the restored PHP error callback receiving a native warning.
             *
             * @return bool confirms the callback consumed the warning
             */
            static function (): bool {
                echo "{\"event\":\"previous-error\"}\n";

                return true;
            },
            E_USER_NOTICE,
        );
        set_exception_handler(
            /**
             * Records restored native uncaught delivery and terminates with its own status.
             *
             * @param Throwable $event the uncaught exception delivered by PHP
             */
            static function (Throwable $event): void {
                echo "{\"event\":\"previous-exception\"}\n";

                exit(42);
            },
        );
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
        $handler = new ErrorHandler(new CallableThrowableHandler(
            /**
             * Records actual recursive delivery in the legacy native failure path.
             *
             * @param Throwable $event the original or reentered failure delivered by PHP
             *
             * @throws RuntimeException the synthetic reporter failure
             */
            static function (Throwable $event): void {
                echo "{\"event\":\"reporter-called\"}\n";

                throw new RuntimeException('synthetic reporter failure');
            },
        ));
        $handler->register();

        throw new RuntimeException('original failure');

    case 'shutdown-throws':
        $handler = new ErrorHandler($reporter, null, new CallableShutdownHandler(
            /** @throws RuntimeException the real native shutdown callback failure */
            static function (): void {
                throw new RuntimeException('synthetic shutdown failure');
            },
        ));
        $handler->register();

        break;

    default:
        throw new InvalidArgumentException('Unknown lifecycle case: ' . $case);
}
