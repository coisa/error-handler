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

use CoiSA\ErrorHandler\Example\ConfiguredErrorHandlerFactory;
use CoiSA\ErrorHandler\Example\RecordingThrowableHandler;

require __DIR__ . '/bootstrap.php';

require_once __DIR__ . '/RecordingThrowableHandler.php';

require_once __DIR__ . '/ConfiguredErrorHandlerFactory.php';

/** @var array{reporter: RecordingThrowableHandler} $configuration */
$configuration = require __DIR__ . '/config/error-handler.php';

$errors = (new ConfiguredErrorHandlerFactory())($configuration);
$failure = new RuntimeException('Synthetic application failure');

$errors->handleThrowable($failure);

if ([$failure] !== $configuration['reporter']->getThrowables()) {
    throw new RuntimeException('Expected one private delivery of the original throwable.');
}

echo 'configured reporting: original delivered once' . PHP_EOL;
