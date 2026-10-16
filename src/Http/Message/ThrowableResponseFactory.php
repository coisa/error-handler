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
        ResponseFactoryInterface $responseFactory
    ) {
        $this->streamFactory = $streamFactory;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Creates a PSR-7 response from a throwable instance.
     *
     * This method SHALL create a response using the provided throwable's code and message,
     * set the HTTP status code appropriately, and include the throwable details in the body.
     *
     * @param \Throwable $throwable the throwable to convert into an HTTP response
     *
     * @return ResponseInterface the generated PSR-7 response
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();

        $statusCode = $this->getStatusCode($code);

        $response = $this->responseFactory->createResponse($statusCode, $message);
        $stream = $this->streamFactory->createStreamFromThrowable($throwable);

        return $response->withBody($stream);
    }

    /**
     * Determines the HTTP status code based on the throwable code.
     *
     * This method SHALL return an integer throwable code in the 400–500 range.
     * String codes, including SQLSTATE values, and other integers SHALL use 500.
     *
     * @param int|string $code the original throwable code, without numeric coercion
     *
     * @return int the HTTP status code to use in the response
     */
    private function getStatusCode($code): int
    {
        return (\is_int($code) && $code >= 400 && $code <= 500) ? $code : 500;
    }
}
