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
 * Captures deliberately public renderer output as an optional compatibility adapter.
 * Middleware reports diagnostics separately before rendering. Consumers MUST NOT
 * pass that diagnostic reporter here again. The provider uses a pure generic renderer by default.
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
     * It SHALL preserve the body "0" and use a generic fallback for empty output.
     * Renderers MUST balance removable buffers and MUST NOT close caller-owned buffers.
     *
     * @param \Throwable $throwable the throwable instance to process
     *
     * @return StreamInterface the rendered response body or a generic fallback
     *
     * @throws \Throwable If rendering, buffer cleanup or stream construction fails.
     *                    A renderer failure takes precedence over any later cleanup failure.
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
            $cleanupFailure = $this->cleanOutputBuffers($initialLevel);
        }

        if (null !== $failure) {
            throw $failure;
        }
        if (null !== $cleanupFailure) {
            throw $cleanupFailure;
        }
        if (false === $buffer || '' === $buffer) {
            $buffer = 'Internal Server Error';
        }

        return $this->streamFactory->createStream($buffer);
    }

    /**
     * Discards reachable removable buffers created during rendering.
     *
     * Cleanup MUST stop at caller-owned or non-removable buffers and when removal
     * makes no progress. Its first failure is returned so the caller can preserve
     * a renderer failure in preference to a later cleanup failure.
     *
     * @param int $initialLevel the caller's output-buffer depth before rendering
     *
     * @return null|\Throwable the first cleanup failure, or null when no removal failed
     */
    private function cleanOutputBuffers(int $initialLevel): ?\Throwable
    {
        $failure = null;

        while (ob_get_level() > $initialLevel) {
            $flags = ob_get_status()['flags'] ?? null;
            if (!\is_int($flags) || ($flags & PHP_OUTPUT_HANDLER_REMOVABLE) === 0) {
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

        return $failure;
    }
}
