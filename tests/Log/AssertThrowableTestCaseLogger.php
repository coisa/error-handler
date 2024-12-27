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
 * @package CoiSA\ErrorHandler\Test\Util
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
     * AssertThrowableTestCaseLogger constructor.
     */
    public function __construct(TestCase $testCase, \Throwable $throwable, string $logLevel = LogLevel::ERROR)
    {
        $this->throwable = $throwable;
        $this->logLevel  = $logLevel;

        self::$testCase = $testCase;
    }

    /**
     * @param mixed  $level
     * @param string $message
     */
    public function log($level, $message, array $context = []): void
    {
        self::$testCase::assertSame($this->logLevel, $level);
        self::$testCase::assertSame((string) $this->throwable, $message);
    }
}
