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

namespace CoiSA\ErrorHandler\Container\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Class ContainerException.
 *
 * Represents a container exception specific to the error handler.
 * This exception SHOULD be used to wrap any throwable related to container operations.
 *
 * @package CoiSA\ErrorHandler\Container\Exception
 */
class ContainerException extends \Exception implements ContainerExceptionInterface
{
    /**
     * Creates a new instance of ContainerException from a throwable.
     *
     * This factory method SHALL wrap a throwable into a ContainerException
     * to provide more context about container-related errors.
     *
     * @param \Throwable $throwable the original throwable to wrap
     *
     * @return self a new instance of ContainerException
     */
    public static function createFromThrowable(\Throwable $throwable): self
    {
        return new self(
            $throwable->getMessage(),
            $throwable->getCode(),
            $throwable,
        );
    }
}
