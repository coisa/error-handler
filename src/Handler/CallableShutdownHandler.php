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

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class CallableShutdownHandler
 *
 * @package CoiSA\ErrorHandler\Handler
 */
final class CallableShutdownHandler implements ShutdownHandlerInterface
{
    /**
     * @var callable
     */
    private $handler;

    /**
     * CallableShutdownHandler constructor.
     *
     * @param callable $handler
     */
    public function __construct(callable $handler)
    {
        $this->handler = $handler;
    }

    /**
     * {@inheritDoc}
     */
    public function handleShutdown(): void
    {
        ($this->handler)();
    }
}
