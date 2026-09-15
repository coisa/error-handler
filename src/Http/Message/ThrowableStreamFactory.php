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

namespace CoiSA\ErrorHandler\Http\Message;

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class ThrowableStreamFactory
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
     * @var StreamFactoryInterface Factory for creating PSR-7 streams.
     */
    private StreamFactoryInterface $streamFactory;

    /**
     * @var ThrowableHandlerInterface Handler for processing throwable instances.
     */
    private ThrowableHandlerInterface $throwableHandler;

    /**
     * Constructs the ThrowableStreamFactory.
     *
     * @param StreamFactoryInterface $streamFactory Factory for creating PSR-7 streams.
     * @param ThrowableHandlerInterface $throwableHandler Handler for throwable instances.
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
     *
     * @param \Throwable $throwable The throwable instance to process.
     *
     * @return StreamInterface The rendered response body or a generic fallback.
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        $initialLevel = ob_get_level();
        $failure = null;
        $buffer = false;
        ob_start();
        try {
            $this->throwableHandler->handleThrowable($throwable);
            $buffer = ob_get_contents();
        } catch (\Throwable $rendererFailure) {
            $failure = $rendererFailure;
        } finally {
            while (ob_get_level() > $initialLevel) {
                $flags = ob_get_status()['flags'] ?? null;
                if (!is_int($flags) || ($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
                    // PHP cannot remove this buffer or reach the buffers below it.
                    break;
                }
                $level = ob_get_level();
                try {
                    ob_end_clean();
                } catch (\Throwable $cleanupFailure) {
                    $failure ??= $cleanupFailure;
                }
                if (ob_get_level() >= $level) {
                    break;
                }
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
        if ($buffer === false || $buffer === '') {
            $buffer = 'Internal Server Error';
        }

        return $this->streamFactory->createStream($buffer);
    }
}
