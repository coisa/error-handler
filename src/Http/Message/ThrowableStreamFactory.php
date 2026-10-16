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

namespace CoiSA\ErrorHandler\Http\Message;

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class ThrowableStreamFactory.
 *
 * Creates PSR-7 streams from Throwable instances.
 * This class SHALL ensure that throwable instances are converted into a
 * readable stream for inclusion in PSR-7 responses or logging mechanisms.
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
final class ThrowableStreamFactory implements ThrowableStreamFactoryInterface
{
    /**
     * @var StreamFactoryInterface factory for creating PSR-7 streams
     */
    private StreamFactoryInterface $streamFactory;

    /**
     * @var ThrowableHandlerInterface handler for processing throwable instances
     */
    private ThrowableHandlerInterface $throwableHandler;

    /**
     * Constructs the ThrowableStreamFactory.
     *
     * @param StreamFactoryInterface    $streamFactory    factory for creating PSR-7 streams
     * @param ThrowableHandlerInterface $throwableHandler handler for throwable instances
     */
    public function __construct(
        StreamFactoryInterface $streamFactory,
        ThrowableHandlerInterface $throwableHandler
    ) {
        $this->streamFactory = $streamFactory;
        $this->throwableHandler = $throwableHandler;
    }

    /**
     * Creates a stream from a Throwable instance.
     *
     * This method SHALL process the throwable using the handler and convert
     * its output into a PSR-7 compatible stream.
     * On capture failure, removable buffers above the entry level are discarded
     * best-effort. Cleanup stops on failure or lack of progress and never replaces
     * the primary throwable. Output-handler side effects cannot be undone.
     * An output callback that terminates the process or triggers an engine fatal
     * can prevent control from returning and the primary failure from being rethrown.
     *
     * @param \Throwable $throwable the throwable instance to process
     *
     * @return StreamInterface the resulting PSR-7 stream containing throwable details
     *
     * @throws \Throwable the original handler, capture or stream-factory failure
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        return $this->streamFactory->createStream($this->captureThrowableOutput($throwable));
    }

    /**
     * Captures reporter output using the existing empty and false-like fallback.
     *
     * When cleanup returns, the primary reporter or capture failure is rethrown.
     * Successful capture retains its existing nested-buffer behavior.
     *
     * @param \Throwable $throwable the throwable passed unchanged to the reporter
     *
     * @return string the captured output or the throwable's diagnostic string
     *
     * @throws \Throwable the original reporter or capture failure
     */
    private function captureThrowableOutput(\Throwable $throwable): string
    {
        $entryLevel = ob_get_level();
        ob_start();

        try {
            $this->throwableHandler->handleThrowable($throwable);

            return ob_get_clean() ?: (string) $throwable;
        } catch (\Throwable $failure) {
            $this->discardOutputBuffers($entryLevel);

            throw $failure;
        }
    }

    /**
     * Discards reachable removable buffers after a primary capture failure.
     *
     * Cleanup does not attempt to close buffers at or below the entry level.
     * It stops on a protected buffer, failed close, absent progress or callback
     * failure. Callback side effects cannot be undone; cleanup failures must
     * not replace the primary failure. Process termination or an engine fatal
     * inside an output callback can prevent cleanup from returning.
     *
     * @param int $entryLevel the output-buffer depth before capture started
     */
    private function discardOutputBuffers(int $entryLevel): void
    {
        try {
            while (ob_get_level() > $entryLevel) {
                $level = ob_get_level();
                $status = ob_get_status();

                if (0 === ($status['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE)) {
                    return;
                }

                if (!ob_end_clean()) {
                    return;
                }

                if (ob_get_level() >= $level) {
                    return;
                }
            }
        } catch (\Throwable $cleanupFailure) {
            // Preserve the primary handler or capture failure.
        }
    }
}
