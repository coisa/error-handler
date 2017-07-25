<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

/**
 * Interface HandlerInterface
 * @package CoiSA\ErrorHandler\Handler
 */
interface HandlerInterface
{
    /**
     * @param \Throwable $throwable
     * @return int Exit code
     */
    public function __invoke(\Throwable $throwable): ?int;
}