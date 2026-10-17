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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Supplies one synthetic application failure to the HTTP recipe.
 */
final class ThrowingRequestHandler implements RequestHandlerInterface
{
    /** @var \Throwable the request's original application failure */
    private \Throwable $failure;

    /**
     * Retain the failure without executing a request.
     *
     * @param \Throwable $failure the synthetic request failure
     */
    public function __construct(\Throwable $failure)
    {
        $this->failure = $failure;
    }

    /**
     * Raise the original failure instead of returning a response.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface this fixture never produces a response
     *
     * @throws \Throwable the identical configured application failure
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        throw $this->failure;
    }
}
