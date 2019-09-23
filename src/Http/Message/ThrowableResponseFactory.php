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

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class ThrowableResponseFactory
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
final class ThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /**
     * @var ThrowableStreamFactoryInterface
     */
    private $streamFactory;

    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * ThrowableResponseFactory constructor.
     *
     * @param ThrowableStreamFactoryInterface $streamFactory
     * @param ResponseFactoryInterface        $responseFactory
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
        $code    = $throwable->getCode();
        $message = $throwable->getMessage();

        $statusCode = $this->getStatusCode($code);

        $response = $this->responseFactory->createResponse($statusCode, $message);
        $stream   = $this->streamFactory->createStreamFromThrowable($throwable);

        return $response->withBody($stream);
    }

    /**
     * @param int $code
     *
     * @return int
     */
    private function getStatusCode(int $code): int
    {
        return $code >= 400 && $code <= 500 ? $code : 500;
    }
}
