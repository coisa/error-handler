<?php

namespace CoiSA\ErrorHandler\Handler;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * Interface ShutdownHandlerInterface
 *
 * @package CoiSA\ErrorHandler\Handler
 */
interface ShutdownHandlerInterface
{
    /**
     * Handle shutdown function
     */
    public function handleShutdown(): void;
}
