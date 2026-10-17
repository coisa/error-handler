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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Delivers a throwable once before delegating to an explicitly selected pure renderer.
 *
 * Reporters own their output and failures; this decorator does not suppress either.
 */
final class ReportingThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ThrowableHandlerInterface the application-owned diagnostic destination */
    private ThrowableHandlerInterface $throwableHandler;

    /** @var ThrowableResponseFactoryInterface the pure renderer invoked after reporting succeeds */
    private ThrowableResponseFactoryInterface $responseFactory;

    /**
     * Configures reporting separately from HTTP representation and lifecycle handling.
     *
     * @param ThrowableHandlerInterface         $throwableHandler the reporter called once per invocation
     * @param ThrowableResponseFactoryInterface $responseFactory  the renderer that must not report again
     */
    public function __construct(
        ThrowableHandlerInterface $throwableHandler,
        ThrowableResponseFactoryInterface $responseFactory,
    ) {
        $this->throwableHandler = $throwableHandler;
        $this->responseFactory = $responseFactory;
    }

    /**
     * Reports the original object, then returns the delegate's exact response.
     *
     * A reporter failure prevents rendering. Neither failure path retries or reports again.
     *
     * @param \Throwable $throwable the original failure passed unchanged to both collaborators
     *
     * @return ResponseInterface the delegate's unmodified response
     *
     * @throws \Throwable the identical reporter or renderer failure
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $this->throwableHandler->handleThrowable($throwable);

        return $this->responseFactory->createResponseFromThrowable($throwable);
    }
}
