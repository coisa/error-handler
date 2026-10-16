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

// Core probes do not require Composer or share the caller's PHP callbacks.
spl_autoload_register(static function (string $class): void {
    $prefix = 'CoiSA\ErrorHandler\\';
    if (0 === strncmp($class, $prefix, strlen($prefix))) {
        require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    }
}, true, true);

require __DIR__ . '/events.php';
