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

namespace CoiSA\ErrorHandler\Http\Message;

use Psr\Http\Message\StreamInterface;

/**
 * Interface ThrowableStreamFactoryInterface
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
interface ThrowableStreamFactoryInterface
{
    /**
     * @param \Throwable $throwable
     *
     * @return StreamInterface
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface;
}
