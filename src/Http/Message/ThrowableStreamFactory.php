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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Class ThrowableStreamFactory
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
final class ThrowableStreamFactory implements ThrowableStreamFactoryInterface
{
    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var ThrowableHandlerInterface
     */
    private $throwableHandler;

    /**
     * ThrowableStreamFactory constructor.
     *
     * @param StreamFactoryInterface    $streamFactory
     * @param ThrowableHandlerInterface $throwableHandler
     */
    public function __construct(
        StreamFactoryInterface $streamFactory,
        ThrowableHandlerInterface $throwableHandler
    ) {
        $this->streamFactory    = $streamFactory;
        $this->throwableHandler = $throwableHandler;
    }

    /**
     * @param \Throwable $throwable
     *
     * @return StreamInterface
     */
    public function createStreamFromThrowable(\Throwable $throwable): StreamInterface
    {
        \ob_start();
        $this->throwableHandler->handleThrowable($throwable);
        $buffer = \ob_get_clean();

        return $this->streamFactory->createStream($buffer);
    }
}
