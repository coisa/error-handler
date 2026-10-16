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

/**
 * Emits one bounded structured observation from a synthetic child fixture.
 *
 * @param string               $name the observation name
 * @param array<string, mixed> $data synthetic fields to inspect in the parent
 */
function fireEvent(string $name, array $data = []): void
{
    fwrite(STDOUT, json_encode(['event' => $name] + $data, JSON_THROW_ON_ERROR) . "\n");
}
