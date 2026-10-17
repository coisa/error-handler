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

$consumerAutoload = $argv[1] ?? null;

if (!is_string($consumerAutoload) || !is_file($consumerAutoload)) {
    throw new InvalidArgumentException('Pass the existing consumer vendor/autoload.php as the first argument.');
}

require $consumerAutoload;
