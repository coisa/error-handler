<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\Http\Message;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface ThrowableResponseFactoryInterface
 *
 * Defines the contract for creating PSR-7 HTTP responses from Throwable instances.
 * Implementing classes SHALL convert a Throwable into an HTTP response,
 * ensuring meaningful HTTP status codes and clear error messages.
 *
 * @package CoiSA\ErrorHandler\Http\Message
 */
interface ThrowableResponseFactoryInterface
{
    /**
     * Creates an HTTP response from a Throwable instance.
     *
     * This method SHALL convert the provided Throwable into an HTTP response
     * that complies with the PSR-7 standard. The response MUST include
     * an appropriate status code, headers, and error message.
     *
     * @param \Throwable $throwable The Throwable instance to convert into a response.
     *
     * @return ResponseInterface The generated HTTP response.
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface;
}
