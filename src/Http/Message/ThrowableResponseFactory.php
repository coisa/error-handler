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
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ThrowableResponseFactory
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
final class ThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * ThrowableStreamFactory constructor.
     *
     * @param StreamFactoryInterface    $streamFactory
     * @param ThrowableHandlerInterface $throwableHandler
     */
    public function __construct(
        ThrowableStreamFactoryInterface $streamFactory,
        ResponseFactoryInterface $responseFactory
    ) {
        $this->streamFactory   = $streamFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * @param \Throwable $throwable
     *
     * @return ResponseInterface
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        // @TODO change to HTTP code
        $code    = $throwable->getCode();
        $message = $throwable->getMessage();

        $response = $this->responseFactory->createResponse($code, $message);
        $stream   = $this->streamFactory->createStreamFromThrowable($throwable);

        return $response->withBody($stream);
    }
}