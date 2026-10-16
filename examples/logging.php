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
use CoiSA\ErrorHandler\Handler\LogThrowableHandler;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

require __DIR__ . '/bootstrap.php';

$logger = new class extends AbstractLogger {
    /** @var list<array{level: mixed, message: mixed, context: array<array-key, mixed>}> Synthetic log records. */
    public array $records = [];

    /**
     * Record arguments without transport or output, using signatures compatible with PSR-3 1.x–3.x.
     *
     * @param mixed                   $level   the level chosen by the application
     * @param object|string           $message the logger's message argument
     * @param array<array-key, mixed> $context structured context retained without coercion
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }
};

$context = ['component' => 'worker', 'exception' => new LogicException('Caller context')];
$failure = new RuntimeException('Synthetic worker failure');
$reporter = new LogThrowableHandler($logger, LogLevel::CRITICAL, $context);

$process = new ErrorHandler($reporter);

$process->handleThrowable($failure);

$empty = new RuntimeException('');
$process->handleThrowable($empty);

exampleCheck(2 === count($logger->records), 'Each adapter invocation must make one logger call.');
exampleCheck(LogLevel::CRITICAL === $logger->records[0]['level'], 'The configured level changed.');
exampleCheck($failure->getMessage() === $logger->records[0]['message'], 'The exact message changed.');
exampleCheck($failure === $logger->records[0]['context']['exception'], 'The original throwable must win its key.');
exampleCheck('worker' === $logger->records[0]['context']['component'], 'Caller context changed.');
exampleCheck($context['exception'] instanceof LogicException, 'Caller context was mutated.');

exampleCheck('' === $logger->records[1]['message'], 'An empty message must stay empty.');
exampleCheck($empty === $logger->records[1]['context']['exception'], 'The second original throwable changed.');

echo 'direct PSR-3 logging, exact messages and original context passed' . PHP_EOL;
