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

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class CallableThrowableHandler
 *
 * Handles throwable instances using a provided callable handler.
 * This class SHALL ensure that the handler callable is invoked
 * whenever a throwable is passed to the `handleThrowable` method.
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class CallableThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * @var callable The handler responsible for processing throwable instances.
     */
    private $handler;

    /**
     * Constructs a CallableThrowableHandler instance.
     *
     * This constructor SHALL accept a callable to handle throwable instances.
     *
     * @param callable $handler The callable that will process the throwable.
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * Handles a throwable instance.
     *
     * This method SHALL invoke the provided callable handler with the given throwable.
     *
     * @param \Throwable $throwable The throwable instance to be handled.
     *
     * @return void
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        ($this->handler)($throwable);
    }
}
