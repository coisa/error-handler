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
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Builds JSON responses; applications own diagnostic reporting and response emission.
 *
 * Integer HTTP error codes 400–599 are preserved; other codes use 500.
 * Debug details require an explicit application choice; generic output is the default.
 * Applications own reporting, factory implementations and response emission.
 */
final class JsonThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ResponseFactoryInterface the application-owned response factory */
    private ResponseFactoryInterface $responseFactory;

    /** @var StreamFactoryInterface the application-owned body factory */
    private StreamFactoryInterface $streamFactory;

    /** @var bool whether trusted application configuration explicitly enables diagnostics */
    private bool $debug;

    /**
     * Configures pure JSON rendering without changing the library's default renderer.
     *
     * @param ResponseFactoryInterface $responseFactory the factory providing HTTP status reason phrases
     * @param StreamFactoryInterface   $streamFactory   the factory providing a new body per response
     * @param bool                     $debug           whether to expose exception metadata for development
     *
     * @throws \LogicException when debug rendering is selected without an available JSON encoder
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        bool $debug = false
    ) {
        if ($debug && !\function_exists('json_encode')) {
            throw new \LogicException('Debug JSON responses require the JSON extension and json_encode().');
        }

        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->debug = $debug;
    }

    /**
     * Creates one response with a generic payload or explicitly selected debug metadata.
     *
     * The throwable message is never used as the response's reason phrase.
     * This method does not report, capture output, install callbacks or emit a response.
     *
     * @param \Throwable $throwable the original failure providing status and optional debug metadata
     *
     * @return ResponseInterface the application response with an application/json body
     *
     * @throws \Throwable     the identical failure raised by an HTTP collaborator
     * @throws \JsonException if controlled debug metadata cannot be encoded
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $code = $throwable->getCode();
        $status = (\is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
        $response = $this->responseFactory->createResponse($status)->withHeader('Content-Type', 'application/json');
        $stream = $this->streamFactory->createStream($this->createBody($throwable));

        if ($this->debug) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        return $response->withBody($stream);
    }

    /**
     * Encodes only selected native metadata, replacing invalid UTF-8 without serializing objects.
     *
     * @param \Throwable $throwable the original failure whose metadata is explicitly selected
     *
     * @return string the complete JSON document without captured reporter output
     *
     * @throws \JsonException if controlled debug metadata cannot be encoded
     */
    private function createBody(\Throwable $throwable): string
    {
        if (!$this->debug) {
            return '{"error":"Request failed"}';
        }

        return json_encode([
            'error' => 'Request failed',
            'exception' => [
                'class' => \get_class($throwable),
                'message' => $throwable->getMessage(),
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
                'trace' => $this->createTraceMetadata($throwable),
            ],
        ], JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    /**
     * Retains only native frame location and call metadata, never arguments or objects.
     *
     * @param \Throwable $throwable the original failure owning the native trace
     *
     * @return list<array<string, mixed>> the metadata-only frames in their original order
     */
    private function createTraceMetadata(\Throwable $throwable): array
    {
        $trace = [];
        foreach ($throwable->getTrace() as $frame) {
            $trace[] = array_intersect_key($frame, [
                'file' => true,
                'line' => true,
                'class' => true,
                'type' => true,
                'function' => true,
            ]);
        }

        return $trace;
    }
}
