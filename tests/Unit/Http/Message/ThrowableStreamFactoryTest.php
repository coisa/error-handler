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

namespace CoiSA\ErrorHandler\Test\Unit\Http\Message;

use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;

/**
 * Verifies captured bytes, exceptional cleanup and primary failure propagation.
 *
 * Dangerous output callbacks remain in the bounded Fire fixtures.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory
 *
 * @uses \CoiSA\ErrorHandler\Handler\CallableThrowableHandler
 */
final class ThrowableStreamFactoryTest extends TestCase
{
    /**
     * Preserves successful bytes, legacy fallback and exact PSR stream delegation.
     *
     * @param string $output the synthetic reporter output
     *
     * @dataProvider provideCapturesOutputAndPreservesLegacyFallbackCases
     */
    public function testCapturesOutputAndPreservesLegacyFallback(string $output): void
    {
        $original = new \RuntimeException('Synthetic incoming event');
        $received = null;

        /**
         * Emits controlled bytes while retaining the reported event.
         *
         * @param \Throwable $throwable the original event received by the reporter
         */
        $reporter = new CallableThrowableHandler(static function (\Throwable $throwable) use (
            $output,
            &$received
        ): void {
            $received = $throwable;
            echo $output;
        });
        $expected = $output ?: (string) $original;
        $stream = (new Psr17Factory())->createStream($expected);
        $messages = $this->createMock(StreamFactoryInterface::class);
        $messages->expects(self::once())->method('createStream')->with($expected)->willReturn($stream);
        $entryLevel = ob_get_level();

        $result = (new ThrowableStreamFactory($messages, $reporter))->createStreamFromThrowable($original);

        self::assertSame($original, $received);
        self::assertSame($stream, $result);
        self::assertSame($expected, (string) $result);
        self::assertSame($entryLevel, ob_get_level());
    }

    /**
     * Supplies ordinary output and both established diagnostic-fallback triggers.
     *
     * @return array<string, array{string}> synthetic reporter bytes
     */
    public static function provideCapturesOutputAndPreservesLegacyFallbackCases(): iterable
    {
        return ['binary content' => ["public\0bytes\n"], 'empty output' => [''], 'zero output' => ['0']];
    }

    /**
     * Captures balanced nested output without consuming caller-owned bytes.
     */
    public function testCapturesBalancedNestedOutputWithoutTouchingCaller(): void
    {
        /**
         * Balances a removable nested buffer before returning to the factory.
         *
         * @param \Throwable $throwable the reported event
         */
        $reporter = new CallableThrowableHandler(static function (\Throwable $throwable): void {
            echo 'outer';
            ob_start();
            echo 'inner';
            ob_end_flush();
        });
        $entryLevel = ob_get_level();
        ob_start();
        echo 'caller:';
        $callerLevel = ob_get_level();

        try {
            $stream = (new ThrowableStreamFactory(new Psr17Factory(), $reporter))
                ->createStreamFromThrowable(new \RuntimeException('Synthetic incoming event'))
            ;

            self::assertSame('outerinner', (string) $stream);
            self::assertSame($callerLevel, ob_get_level());
            self::assertSame('caller:', ob_get_contents());
        } finally {
            while (ob_get_level() > $entryLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * Discards partial reporter output while preserving its distinct failure and caller.
     *
     * @param int $nestedBuffers the extra removable buffers opened before failure
     *
     * @dataProvider provideCleansFailedCaptureAndPreservesPrimaryAndCallerCases
     */
    public function testCleansFailedCaptureAndPreservesPrimaryAndCaller(int $nestedBuffers): void
    {
        $original = new \Error('Synthetic incoming event');
        $primary = new \RuntimeException('Synthetic reporter failure');
        $received = null;

        /**
         * Opens only ordinary removable buffers, then fails after writing partial output.
         *
         * @param \Throwable $throwable the original event supplied for reporting
         *
         * @throws \RuntimeException the distinct primary reporter failure
         */
        $reporter = new CallableThrowableHandler(static function (\Throwable $throwable) use (
            $nestedBuffers,
            $primary,
            &$received
        ): void {
            $received = $throwable;
            echo 'partial capture';
            for ($index = 0; $index < $nestedBuffers; ++$index) {
                ob_start();
                echo 'partial nested';
            }

            throw $primary;
        });
        $messages = $this->createMock(StreamFactoryInterface::class);
        $messages->expects(self::never())->method('createStream');
        $entryLevel = ob_get_level();
        ob_start();
        echo 'caller:';
        $callerLevel = ob_get_level();
        $caught = null;

        try {
            try {
                (new ThrowableStreamFactory($messages, $reporter))->createStreamFromThrowable($original);
            } catch (\Throwable $failure) {
                $caught = $failure;
            }

            self::assertSame($original, $received);
            self::assertSame($primary, $caught);
            self::assertSame($callerLevel, ob_get_level());
            self::assertSame('caller:', ob_get_contents());
        } finally {
            while (ob_get_level() > $entryLevel) {
                ob_end_clean();
            }
        }
    }

    /**
     * Exercises factory-only capture and multiple nested removable scopes.
     *
     * @return array<string, array{int}> extra nested-buffer counts
     */
    public static function provideCleansFailedCaptureAndPreservesPrimaryAndCallerCases(): iterable
    {
        return ['factory buffer' => [0], 'nested buffers' => [2]];
    }

    /**
     * Leaves the caller alone when the reporter has already closed capture.
     */
    public function testPreservesCallerWhenReporterAlreadyClosedCapture(): void
    {
        $primary = new \RuntimeException('Synthetic reporter failure');

        /**
         * Closes the factory scope before throwing the configured primary failure.
         *
         * @param \Throwable $throwable the reported event
         *
         * @throws \RuntimeException the configured primary failure
         */
        $reporter = new CallableThrowableHandler(static function (\Throwable $throwable) use ($primary): void {
            ob_end_clean();

            throw $primary;
        });
        $entryLevel = ob_get_level();
        ob_start();
        echo 'caller:';
        $callerLevel = ob_get_level();
        $caught = null;

        try {
            try {
                (new ThrowableStreamFactory(new Psr17Factory(), $reporter))
                    ->createStreamFromThrowable(new \RuntimeException('Synthetic incoming event'))
                ;
            } catch (\Throwable $failure) {
                $caught = $failure;
            }

            self::assertSame($primary, $caught);
            self::assertSame($callerLevel, ob_get_level());
            self::assertSame('caller:', ob_get_contents());
        } finally {
            while (ob_get_level() > $entryLevel) {
                ob_end_clean();
            }
        }
    }

    /** Verifies successful unbalanced capture retains its existing inner-body/outer-buffer behavior. */
    public function testUnbalancedSuccessRetainsLegacyOuterBuffer(): void
    {
        $reporter = new CallableThrowableHandler(
            /**
             * Leaves one ordinary nested scope for the successful legacy capture to consume.
             *
             * @param \Throwable $event the supplied reporting event
             */
            static function (\Throwable $event): void {
                echo 'outer';
                ob_start();
                echo 'inner';
            },
        );
        $entry = ob_get_level();
        ob_start();
        echo 'caller:';
        $caller = ob_get_level();

        try {
            $stream = (new ThrowableStreamFactory(new Psr17Factory(), $reporter))
                ->createStreamFromThrowable(new \RuntimeException('incoming'))
            ;
            self::assertSame('inner', (string) $stream);
            self::assertSame($caller + 1, ob_get_level());
            self::assertSame('outer', ob_get_contents());
            ob_end_clean();
            self::assertSame('caller:', ob_get_contents());
        } finally {
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }
    }

    /** Verifies PSR stream failures propagate after successful capture has already closed. */
    public function testStreamFactoryFailureDoesNotLeaveTheCaptureOpen(): void
    {
        $original = new \Error('incoming');
        $primary = new \RuntimeException('stream creation');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->method('createStream')->willThrowException($primary);
        $entry = ob_get_level();

        try {
            (new ThrowableStreamFactory($streams, $reporter))->createStreamFromThrowable($original);
            self::fail('The PSR stream factory failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);
            self::assertSame($entry, ob_get_level());
        }
    }
}
