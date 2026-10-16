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

use Psr\Http\Message\StreamInterface;

/**
 * Interface ThrowableStreamFactoryInterface.
 *
 * Defines the contract for creating PSR-7 streams from Throwable instances.
 * Implementing classes SHALL ensure that throwable details are converted
 * into a valid PSR-7 stream representation, suitable for logging or HTTP responses.
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
interface ThrowableStreamFactoryInterface
{
    /**
     * Creates a stream from a Throwable instance.
     *
     * This method SHALL process the provided Throwable and convert its details
     * into a PSR-7 compliant StreamInterface instance.
     *
     * @param \Throwable $throwable the throwable instance to convert into a stream
     *
     * @return StreamInterface the generated PSR-7 stream containing throwable details
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface;
}
