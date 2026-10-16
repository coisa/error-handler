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
     *
     * @param \Throwable $throwable the throwable instance to process
     *
     * @return StreamInterface the resulting PSR-7 stream containing throwable details
     *
     * @throws \Throwable the original handler, capture or stream-factory failure
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        $bufferLevel = ob_get_level();
        ob_start();

        try {
            $this->throwableHandler->handleThrowable($throwable);
            $buffer = ob_get_clean() ?: (string) $throwable;
        } catch (\Throwable $failure) {
            try {
                while (ob_get_level() > $bufferLevel) {
                    $level = ob_get_level();
                    $status = ob_get_status();
                    if (
                        0 === ($status['flags'] & PHP_OUTPUT_HANDLER_REMOVABLE)
                        || !ob_end_clean() || ob_get_level() >= $level
                    ) {
                        break;
                    }
                }
            } catch (\Throwable $cleanupFailure) {
                // Preserve the primary handler or capture failure.
            }

            throw $failure;
        }

        return $this->streamFactory->createStream($buffer);
    }
}
