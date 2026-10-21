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
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Builds JSON Problem Details; applications own reporting and response emission.
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

    /** @var SerializerInterface the application serializer configured with a problem normalizer and JSON encoder */
    private SerializerInterface $serializer;

    /** @var bool whether trusted application configuration explicitly enables diagnostics */
    private bool $debug;

    /**
     * Configures pure Problem Details rendering with an explicit serializer.
     *
     * @param ResponseFactoryInterface $responseFactory the factory providing HTTP status reason phrases
     * @param StreamFactoryInterface   $streamFactory   the factory providing a new body per response
     * @param SerializerInterface      $serializer      the serializer using ProblemNormalizer and JsonEncoder
     * @param bool                     $debug           whether to expose exception metadata for development
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        SerializerInterface $serializer,
        bool $debug = false,
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->serializer = $serializer;
        $this->debug = $debug;
    }

    /**
     * Creates one response with a generic payload or explicitly selected debug metadata.
     *
     * The throwable message is never used as the response's reason phrase.
     * This method does not report, capture output, install callbacks or emit a response.
     *
     * @param \Throwable             $throwable the original failure providing status and optional debug metadata
     * @param ServerRequestInterface $request   the request; diagnostics use trusted configuration only
     *
     * @return ResponseInterface the application response with a JSON Problem Details body
     *
     * @throws \Throwable the identical failure raised by an HTTP or serializer collaborator
     */
    public function createResponseFromThrowable(
        \Throwable $throwable,
        ServerRequestInterface $request,
    ): ResponseInterface {
        $code = $throwable->getCode();
        $status = (\is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/problem+json')
        ;
        $stream = $this->streamFactory->createStream($this->serializer->serialize(
            $this->createProblem($throwable, $status),
            'json',
            [
                'debug' => $this->debug,
                'type' => 'about:blank',
                'title' => $response->getReasonPhrase(),
                'status' => $status,
                'json_encode_options' => JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR,
            ],
        ));

        if ($this->debug) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        return $response->withBody($stream);
    }

    /**
     * Selects native metadata without traversing exception objects or trace arguments.
     *
     * @param \Throwable $throwable the original failure whose metadata is explicitly selected
     * @param int        $status    the library's selected HTTP error status
     *
     * @return FlattenException a detached problem value without a previous exception chain
     */
    private function createProblem(\Throwable $throwable, int $status): FlattenException
    {
        return (new FlattenException())
            ->setStatusCode($status)
            ->setStatusText('Request failed')
            ->setMessage($this->debug ? $throwable->getMessage() : 'Request failed')
            ->setClass($this->debug ? $throwable::class : '')
            ->setTrace(
                $this->debug ? $this->createTraceMetadata($throwable) : [],
                $this->debug ? $throwable->getFile() : '',
                $this->debug ? $throwable->getLine() : 0,
            )
        ;
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
