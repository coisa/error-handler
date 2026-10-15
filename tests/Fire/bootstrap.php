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

// Keep the crash tests independent from PHPUnit and optional integrations.
spl_autoload_register(static function (string $class): void {
    $prefix = 'CoiSA\\ErrorHandler\\';
    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
});

/**
 * Emits one structured child-process observation for the bounded fire-test runner.
 *
 * @param string $name The observation name expected by the parent runner.
 * @param array<string, mixed> $data Synthetic observation fields.
 */
function event(string $name, array $data = []): void
{
    echo json_encode(['event' => $name] + $data, JSON_THROW_ON_ERROR) . "\n";
}
