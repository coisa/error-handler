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

namespace CoiSA\ErrorHandler\Example;

use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Application policy: build a fixed public response after middleware reports privately.
 */
final class PublicThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ResponseFactoryInterface the application-supplied PSR-17 response factory */
    private ResponseFactoryInterface $responses;

    /** @var StreamFactoryInterface the application-supplied PSR-17 stream factory */
    private StreamFactoryInterface $streams;

    /**
     * Compose pure response construction without reporting or installing callbacks.
     *
     * @param ResponseFactoryInterface $responses the application's response factory
     * @param StreamFactoryInterface   $streams   the application's stream factory
     */
    public function __construct(
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams,
    ) {
        $this->responses = $responses;
        $this->streams = $streams;
    }

    /**
     * Return a generic body without copying exception details or reporting again.
     *
     * @param \Throwable $throwable the original failure, already reported by middleware
     *
     * @return ResponseInterface a status-500 response with an application-owned body
     *
     * @throws \Throwable when a supplied PSR-17 factory fails
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        return $this->responses->createResponse(500)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream('{"error":"Request failed"}'))
        ;
    }
}
