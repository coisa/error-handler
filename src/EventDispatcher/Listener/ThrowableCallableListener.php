<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\EventDispatcher\Listener;

/**
 * Class ThrowableCallableListener
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Listener
 */
final class ThrowableCallableListener
{
    /**
     * @var callable
     */
    private $callable;

    /**
     * ThrowableCallableListener constructor.
     *
     * @param callable $callable
     */
    public function __construct(callable $callable)
    {
        $this->callable = $callable;
    }

    /**
     * @param \Throwable $throwable
     *
     * @return \Throwable
     */
    public function __invoke(\Throwable $throwable): \Throwable
    {
        ($this->callable)($throwable);

        return $throwable;
    }
}
