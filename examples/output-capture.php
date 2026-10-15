<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link https://github.com/coisa/error-handler
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license https://opensource.org/licenses/MIT MIT License
 */

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;

require dirname(__DIR__) . '/vendor/autoload.php';

// Optional compatibility adapter: only deliberately public output belongs here.
// Middleware now reports privately before rendering; do not inject its reporter again.
$publicRenderer = new CallableThrowableHandler(static function (): void {
    echo 'Public failure page';
});

$streams = new ThrowableStreamFactory(new Psr17Factory(), $publicRenderer);

$body = $streams->createStreamFromThrowable(new RuntimeException('Private diagnostics'));

printf("Captured body: %s\n", (string) $body);
