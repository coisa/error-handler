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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Application policy: report privately once, then build a fixed public response.
 */
final class PublicThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ThrowableHandlerInterface the application's private diagnostic sink */
    private ThrowableHandlerInterface $reporter;

    /** @var ResponseFactoryInterface the application-supplied PSR-17 response factory */
    private ResponseFactoryInterface $responses;

    /** @var StreamFactoryInterface the application-supplied PSR-17 stream factory */
    private StreamFactoryInterface $streams;

    /**
     * Compose reporting and response construction without installing callbacks.
     *
     * @param ThrowableHandlerInterface $reporter  the private reporter
     * @param ResponseFactoryInterface  $responses the application's response factory
     * @param StreamFactoryInterface    $streams   the application's stream factory
     */
    public function __construct(
        ThrowableHandlerInterface $reporter,
        ResponseFactoryInterface $responses,
        StreamFactoryInterface $streams
    ) {
        $this->reporter = $reporter;
        $this->responses = $responses;
        $this->streams = $streams;
    }

    /**
     * Report once before returning a generic body without exception details.
     *
     * @param \Throwable $throwable the original failure, kept in private diagnostics
     *
     * @return ResponseInterface a status-500 response with an application-owned JSON body
     *
     * @throws \Throwable when reporting or a PSR-17 collaborator fails
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $this->reporter->handleThrowable($throwable);

        return $this->responses->createResponse(500)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streams->createStream('{"error":"Request failed"}'))
        ;
    }
}
