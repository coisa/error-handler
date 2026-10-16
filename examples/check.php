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
 * Fail an example when its observed API contract differs from the documented behavior.
 *
 * @param bool   $condition the observed contract assertion
 * @param string $message   the synthetic failure diagnostic
 *
 * @throws RuntimeException when the assertion fails
 *
 * @phpstan-assert true $condition
 */
function exampleCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}
