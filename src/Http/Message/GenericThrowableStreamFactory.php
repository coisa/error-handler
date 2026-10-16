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

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Produces a controlled public body without reporting or capturing output.
 */
final class GenericThrowableStreamFactory implements ThrowableStreamFactoryInterface
{
    /**
     * Constructs a renderer using the application's PSR-17 stream factory.
     *
     * @param StreamFactoryInterface $streams creates response body streams
     */
    public function __construct(private StreamFactoryInterface $streams)
    {
    }

    /**
     * Creates the generic response body without disclosing throwable diagnostics.
     *
     * @param \Throwable $throwable the private failure whose details remain undisclosed
     *
     * @return StreamInterface a new stream containing the generic public message
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        return $this->streams->createStream('Internal Server Error');
    }
}
