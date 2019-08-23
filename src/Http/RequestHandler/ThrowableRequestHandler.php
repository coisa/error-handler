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

namespace CoiSA\ErrorHandler\Http\RequestHandler;

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Class ThrowableRequestHandler
 *
 * @package CoiSA\ErrorHandler\Http\RequestHandler
 */
final class ThrowableRequestHandler implements RequestHandlerInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    /**
     * @var StreamFactoryInterface
     */
    private $streamFactory;

    /**
     * ThrowableHandlerMiddleware constructor.
     *
     * @param ThrowableHandlerInterface $throwableHandler
     * @param ResponseFactoryInterface  $responseFactory
     * @param StreamFactoryInterface    $streamFactory
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory   = $streamFactory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $throwable = $request->getAttribute(self::class);

        \ob_start();
        $this->handleThrowable($throwable);
        $buffer = \ob_get_clean();

        $body = $this->streamFactory->createStream($buffer);

        return $this->responseFactory->createResponse(
            $throwable->getCode(),
            $throwable->getMessage()
        )->withBody($body);
    }
}
