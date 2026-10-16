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
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\Test\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class AssertThrowableTestCaseLogger.
 *
 * @package CoiSA\ErrorHandler\Test\Log
 */
final class AssertThrowableTestCaseLogger extends AbstractLogger
{
    /**
     * @var \Throwable
     */
    private $throwable;

    /**
     * @var string
     */
    private $logLevel;

    /**
     * @var TestCase
     */
    private static $testCase;

    /**
     * Capture the throwable and expected level asserted by each log call.
     *
     * @param TestCase $testCase The test case receiving assertion counts.
     * @param \Throwable $throwable The throwable whose string representation is expected.
     * @param string $logLevel The exact expected PSR-3 log level.
     */
    public function __construct(TestCase $testCase, \Throwable $throwable, string $logLevel = LogLevel::ERROR)
    {
        $this->throwable = $throwable;
        $this->logLevel  = $logLevel;

        self::$testCase = $testCase;
    }

    /**
     * Assert that a diagnostic report preserves the expected level and throwable text.
     *
     * @param mixed  $level
     * @param string $message
     * @param array<string, mixed> $context Optional PSR-3 context, unused by this fixture.
     */
    public function log($level, $message, array $context = []): void
    {
        self::$testCase::assertSame($this->logLevel, $level);
        self::$testCase::assertSame((string) $this->throwable, $message);
    }
}
