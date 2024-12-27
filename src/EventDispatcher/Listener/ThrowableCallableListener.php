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

namespace CoiSA\ErrorHandler\EventDispatcher\Listener;

/**
 * Class ThrowableCallableListener
 *
 * Handles throwable instances using a callable. This listener SHALL execute
 * the provided callable when invoked with a Throwable instance.
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class ThrowableCallableListener
{
    /**
     * @var callable A callable used to process throwable instances.
     */
    private $callable;

    /**
     * Constructs the ThrowableCallableListener.
     *
     * @param callable $callable A callable that will handle throwable instances.
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * Invokes the listener with a Throwable instance.
     *
     * This method SHALL execute the provided callable with the given throwable.
     * It MUST return the same Throwable instance after processing.
     *
     * @param \Throwable $throwable The throwable instance to be processed.
     *
     * @return \Throwable The processed throwable instance.
     */
    public function __invoke(\Throwable $throwable): \Throwable
    {
        ($this->callable)($throwable);

        return $throwable;
    }
}
