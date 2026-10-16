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
     * This method SHALL retain integer codes from 400 through 500, using 500 otherwise.
     * A message valid for an HTTP reason phrase SHALL be passed unchanged; forbidden
     * control bytes SHALL select the PSR-17 factory's default reason instead.
     * The configured stream factory SHALL receive the original throwable once.
     *
     * @param \Throwable $throwable the throwable to convert into an HTTP response
     *
     * @return ResponseInterface the generated PSR-7 response
     *
     * @throws \Throwable when the response or stream factory fails
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $code = $throwable->getCode();
        $message = $throwable->getMessage();

        $statusCode = $this->getStatusCode($code);

        $response = $this->createResponse($statusCode, $message);
        $stream = $this->streamFactory->createStreamFromThrowable($throwable);

        return $response->withBody($stream);
    }

    /**
     * Creates a response with a valid reason or the PSR-17 implementation's default.
     *
     * HTAB, spaces, visible ASCII and opaque high bytes SHALL remain unchanged.
     * A valid empty message SHALL still be passed explicitly as in the existing API.
     * A forbidden control byte SHALL omit the reason argument entirely.
     *
     * @param int    $statusCode the resolved legacy HTTP status
     * @param string $message    the original throwable message
     *
     * @return ResponseInterface the response before attaching the throwable stream
     *
     * @throws \Throwable when the application response factory fails
     *
     * @see https://www.rfc-editor.org/rfc/rfc9112.html#section-4
     */
    private function createResponse(int $statusCode, string $message): ResponseInterface
    {
        if (1 === preg_match('/[\x00-\x08\x0A-\x1F\x7F]/', $message)) {
            return $this->responseFactory->createResponse($statusCode);
        }

        return $this->responseFactory->createResponse($statusCode, $message);
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
