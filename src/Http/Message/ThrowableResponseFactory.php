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

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class ThrowableResponseFactory.
 *
 * Creates PSR-7 HTTP responses from throwable instances.
 * This class SHALL use a ThrowableStreamFactoryInterface and ResponseFactoryInterface
 * to create consistent and meaningful HTTP responses based on throwables.
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
final class ThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /**
     * @var ThrowableStreamFactoryInterface the stream factory for throwable content
     */
    private ThrowableStreamFactoryInterface $streamFactory;

    /**
     * @var ResponseFactoryInterface the factory for creating HTTP responses
     */
    private ResponseFactoryInterface $responseFactory;

    /**
     * Constructs the ThrowableResponseFactory.
     *
     * @param ThrowableStreamFactoryInterface $streamFactory   the factory for throwable content streams
     * @param ResponseFactoryInterface        $responseFactory the factory for creating HTTP responses
     */
    public function __construct(
        ThrowableStreamFactoryInterface $streamFactory,
        ResponseFactoryInterface $responseFactory,
    ) {
        $this->streamFactory = $streamFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Creates a PSR-7 response from a throwable instance.
     *
     * This method SHALL map the throwable's code to an HTTP status, use the factory's
     * standard reason phrase, and include the stream factory's public response body.
     *
     * @param \Throwable             $throwable the throwable to convert into an HTTP response
     * @param ServerRequestInterface $request   the incoming request; generic rendering is request-independent
     *
     * @return ResponseInterface the generated PSR-7 response
     */
    public function createResponseFromThrowable(
        \Throwable $throwable,
        ServerRequestInterface $request,
    ): ResponseInterface {
        $code = $throwable->getCode();
        $statusCode = $this->getStatusCode($code);

        $response = $this->responseFactory->createResponse($statusCode);
        $stream = $this->streamFactory->createStreamFromThrowable($throwable);

        return $response->withBody($stream);
    }

    /**
     * Determines the HTTP status code based on the throwable code.
     *
     * This method SHALL return an integer throwable code in the 400–599 range.
     * Otherwise, it SHALL return a default status code of 500.
     *
     * @param int|string $code the throwable code (PDO can use a SQLSTATE string)
     *
     * @return int the HTTP status code to use in the response
     */
    private function getStatusCode(int|string $code): int
    {
        return (\is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
    }
}
