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

namespace CoiSA\ErrorHandler\Container\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 *
 * Represents an exception thrown when a requested entry is not found in the container.
 * This exception MUST be used in cases where a container cannot resolve or find an entry.
 *
 * @package CoiSA\ErrorHandler\Container\Exception
 */
final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
    /**
     * Creates a new instance of NotFoundException from a throwable.
     *
     * This factory method SHALL wrap a throwable into a NotFoundException
     * to provide more context about the missing entry.
     *
     * @param \Throwable $throwable The original throwable to wrap.
     *
     * @return self A new instance of NotFoundException.
     */
    public static function createFromThrowable(\Throwable $throwable): self
    {
        return new self(
            $throwable->getMessage(),
            $throwable->getCode(),
            $throwable
        );
    }
}
