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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Verifies captured bytes, exceptional cleanup and primary failure propagation.
 *
 * Dangerous output callbacks are verified by PHPUnit Process tests and bounded fixtures.
 *
 * @internal
 */
#[CoversClass(ThrowableStreamFactory::class)]
#[UsesClass(CallableThrowableHandler::class)]
final class ThrowableStreamFactoryTest extends TestCase
{
    /**
     * Preserves successful bytes, the generic fallback and exact PSR stream delegation.
     *
     * @param string $output the synthetic reporter output
     */
    #[DataProvider('provideCapturesOutputAndPreservesLegacyFallbackCases')]
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
        $expected = '' === $output ? 'Internal Server Error' : $output;
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
     * Supplies ordinary bytes, empty output and the nonempty string zero.
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
     */
    #[DataProvider('provideCleansFailedCaptureAndPreservesPrimaryAndCallerCases')]
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

    /** Verifies successful unbalanced rendering discards all reachable renderer-owned buffers. */
    public function testUnbalancedSuccessCleansRendererBuffers(): void
    {
        $reporter = new CallableThrowableHandler(
            /**
             * Leaves one ordinary nested scope for the successful capture to consume.
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
            self::assertSame($caller, ob_get_level());
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
        $streams->expects(self::once())->method('createStream')->willThrowException($primary);
        $entry = ob_get_level();

        try {
            (new ThrowableStreamFactory($streams, $reporter))->createStreamFromThrowable($original);
            self::fail('The PSR stream factory failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($primary, $caught);
            self::assertSame($entry, ob_get_level());
        }
    }

    /** Verifies a native throwing output handler preserves primary failure and caller-owned scopes. */
    public function testCleanupCallbackFailurePreservesPrimaryAndCaller(): void
    {
        $original = new \Error('incoming report');
        $primary = new \RuntimeException('primary reporter failure');
        $secondary = new \LogicException('secondary callback failure');
        $callbackCalls = 0;
        $callerCalls = 0;
        $received = null;
        $reporter = new CallableThrowableHandler(
            /**
             * Opens a removable scope whose native callback fails before reporting fails.
             *
             * @param \Throwable $event the original event supplied for reporting
             *
             * @throws \RuntimeException the primary reporter failure
             */
            static function (\Throwable $event) use ($primary, $secondary, &$callbackCalls, &$received): void {
                $received = $event;
                ob_start(
                    /**
                     * Throws once from the actual native close, without modifying buffer structure.
                     *
                     * @param string $buffer the nested reporter bytes
                     *
                     * @return string unchanged bytes on any later invocation
                     *
                     * @throws \LogicException the distinct first output-handler failure
                     */
                    static function (string $buffer) use ($secondary, &$callbackCalls): string {
                        if (1 === ++$callbackCalls) {
                            throw $secondary;
                        }

                        return $buffer;
                    },
                );
                echo 'discard nested';

                throw $primary;
            },
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $entry = ob_get_level();
        ob_start();
        echo 'C0:';
        ob_start(
            /**
             * Records premature invocation of a caller-owned scope.
             *
             * @param string $buffer the caller's original bytes
             *
             * @return string the same caller bytes
             */
            static function (string $buffer) use (&$callerCalls): string {
                ++$callerCalls;

                return $buffer;
            },
        );
        echo 'C1:';
        $callerLevel = ob_get_level();
        $caught = null;

        try {
            try {
                (new ThrowableStreamFactory($streams, $reporter))->createStreamFromThrowable($original);
            } catch (\Throwable $failure) {
                $caught = $failure;
            }
            self::assertSame($original, $received);
            self::assertSame($primary, $caught);
            self::assertSame(1, $callbackCalls);
            self::assertSame($callerLevel, ob_get_level());
            self::assertSame(0, $callerCalls);
            self::assertSame('C1:', ob_get_contents());
            ob_end_flush();
            self::assertSame('C0:C1:', ob_get_contents());
            self::assertSame(1, $callerCalls);
        } finally {
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }
        self::assertSame($entry, ob_get_level());
    }

    /** Verifies a cleanup-only callback failure propagates after successful rendering. */
    public function testCleanupOnlyFailurePropagatesWithoutCreatingAStream(): void
    {
        $original = new \Error('private diagnostic');
        $secondary = new \LogicException('public renderer cleanup');
        $calls = 0;
        $received = null;
        $renderer = new CallableThrowableHandler(
            /**
             * Returns normally after opening a removable callback-owned output scope.
             *
             * @param \Throwable $event the unchanged input supplied for public rendering
             */
            static function (\Throwable $event) use ($secondary, &$received, &$calls): void {
                $received = $event;
                ob_start(
                    /**
                     * Fails once while PHP discards the renderer's native output scope.
                     *
                     * @param string $bytes the public bytes being discarded
                     *
                     * @return string unchanged bytes on a later native callback
                     *
                     * @throws \LogicException the original cleanup failure
                     */
                    static function (string $bytes) use ($secondary, &$calls): string {
                        if (1 === ++$calls) {
                            throw $secondary;
                        }

                        return $bytes;
                    },
                );
                echo 'public template';
            },
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $entry = ob_get_level();
        ob_start();
        echo 'caller:';
        $caller = ob_get_level();

        try {
            try {
                (new ThrowableStreamFactory($streams, $renderer))->createStreamFromThrowable($original);
                self::fail('The original cleanup failure must propagate.');
            } catch (\LogicException $caught) {
                self::assertSame($secondary, $caught);
            }
            self::assertSame($original, $received);
            self::assertSame(1, $calls);
            self::assertSame($caller, ob_get_level());
            self::assertSame('caller:', ob_get_contents());
        } finally {
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }
    }

    /**
     * Stops bounded cleanup when a native handler destructor replaces its removed buffer.
     *
     * The replacement remains removable for fixture cleanup; the caller's bytes and
     * callback remain untouched until this test explicitly discards its own scopes.
     */
    public function testCleanupStopsWhenAHandlerDestructorReplacesItsBuffer(): void
    {
        $original = new \Error('incoming event before native destruction');
        $primary = new \RuntimeException('renderer failure before native destruction');
        $received = null;

        /** @var \ArrayObject<string, int> $events observed native callback and destructor counts */
        $events = new \ArrayObject(['callback' => 0, 'destructor' => 0, 'caller' => 0]);
        $renderer = new CallableThrowableHandler(
            /**
             * Leaves the native output stack as the sole owner of its invokable handler.
             *
             * @param \Throwable $event the unchanged incoming event
             *
             * @throws \RuntimeException the primary renderer failure
             */
            static function (\Throwable $event) use ($primary, $events, &$received): void {
                $received = $event;
                ob_start(
                    // Replaces its native output scope only when PHP releases this object.
                    new class ($events) {
                        /**
                         * Shares observations without retaining a reference to this handler.
                         *
                         * @param \ArrayObject<string, int> $events native callback/destructor counts
                         */
                        public function __construct(private \ArrayObject $events)
                        {
                        }

                        /**
                         * Receives bytes during the actual native buffer close.
                         *
                         * @param string $bytes the renderer-owned output being discarded
                         *
                         * @return string the same bytes, with no stack manipulation in the callback
                         */
                        public function __invoke(string $bytes): string
                        {
                            ++$this->events['callback'];

                            return $bytes;
                        }

                        /** Opens a removable replacement after PHP has popped the original buffer. */
                        public function __destruct()
                        {
                            ++$this->events['destructor'];
                            ob_start();
                            echo 'replacement renderer bytes';
                        }
                    },
                );
                echo 'discarded renderer bytes';

                throw $primary;
            },
        );
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::never())->method('createStream');
        $entry = ob_get_level();
        ob_start(
            /**
             * Records premature cleanup of the caller while retaining its original bytes.
             *
             * @param string $bytes the caller-owned output
             *
             * @return string unchanged caller-owned bytes
             */
            static function (string $bytes) use ($events): string {
                ++$events['caller'];

                return $bytes;
            },
        );
        echo 'caller-owned bytes';
        $caller = ob_get_level();

        try {
            try {
                (new ThrowableStreamFactory($streams, $renderer))->createStreamFromThrowable($original);
                self::fail('The primary renderer failure must propagate.');
            } catch (\RuntimeException $caught) {
                self::assertSame($primary, $caught);
            }

            self::assertSame($original, $received);
            self::assertSame(1, $events['callback']);
            self::assertSame(1, $events['destructor']);
            self::assertSame(0, $events['caller']);
            self::assertSame($caller + 2, ob_get_level());
            self::assertSame('replacement renderer bytes', ob_get_contents());
            $flags = ob_get_status()['flags'];
            self::assertIsInt($flags);
            self::assertSame(PHP_OUTPUT_HANDLER_REMOVABLE, $flags & PHP_OUTPUT_HANDLER_REMOVABLE);

            ob_end_clean();
            ob_end_clean();

            self::assertSame($caller, ob_get_level());
            self::assertSame('caller-owned bytes', ob_get_contents());
            self::assertSame(0, $events['caller']);
        } finally {
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }

        self::assertSame($entry, ob_get_level());
        self::assertSame(1, $events['caller']);
    }
}
