<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler;

/**
 * Interface ErrorHandlerInterface
 * @package CoiSA\ErrorHandler
 */
interface ErrorHandlerInterface
{
    /**
     * Register error-handler
     */
    public function register(): void;

    /**
     * Unregister error-handler
     */
    public function unregister(): void;
}
