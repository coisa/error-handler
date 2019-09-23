<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Test\Log;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * Class AssertThrowableTestCaseLogger
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
     *
     * @param TestCase   $testCase
     * @param \Throwable $throwable
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
     * @param array  $context
     */
    public function log($level, $message, array $context = []): void
    {
        self::$testCase::assertSame($this->logLevel, $level);
        self::$testCase::assertSame((string) $this->throwable, $message);
    }
}
