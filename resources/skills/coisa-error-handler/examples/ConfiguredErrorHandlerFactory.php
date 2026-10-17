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

namespace CoiSA\ErrorHandler\Example;

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;

/**
 * Demonstrates application-owned configuration without extending library APIs.
 */
final class ConfiguredErrorHandlerFactory
{
    /**
     * Compose a fresh core handler from flat application configuration.
     *
     * Overrides replace matching keys; no callback is installed or reporter invoked.
     *
     * @param array<string, mixed> $configuration application configuration
     * @param array<string, mixed> $overrides     explicit application or test overrides
     *
     * @return ErrorHandler a new handler using the selected interface or callable reporter
     *
     * @throws \InvalidArgumentException when the selected reporter is absent or invalid
     */
    public function __invoke(array $configuration, array $overrides = []): ErrorHandler
    {
        $settings = array_replace($configuration, $overrides);
        $reporter = $settings['reporter'] ?? null;

        if (!$reporter instanceof ThrowableHandlerInterface && !\is_callable($reporter)) {
            throw new \InvalidArgumentException('Configure an interface or callable reporter.');
        }

        return new ErrorHandler($reporter);
    }
}
