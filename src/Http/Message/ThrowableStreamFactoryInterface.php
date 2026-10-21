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
 * Defines the contract for creating PSR-7 streams for Throwable instances.
 * Implementations choose the representation, which may be generic and omit
 * diagnostic details. Creating a stream does not imply reporting or emission.
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
interface ThrowableStreamFactoryInterface
{
    /**
     * Creates a stream from a Throwable instance.
     *
     * The representation may omit the throwable's diagnostic details according
     * to the application's public-output policy.
     *
     * @param \Throwable $throwable the throwable instance to convert into a stream
     *
     * @return StreamInterface the generated PSR-7 stream representation
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface;
}
