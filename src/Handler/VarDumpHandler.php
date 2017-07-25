<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

use CoiSA\ErrorHandler\Helper\DebugHelper;

/**
 * Class VarDumpHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class VarDumpHandler extends DebugHandler
{
    /**
     * @param \Throwable $throwable
     * @return int
     */
    public function __invoke(\Throwable $throwable): ?int
    {
        echo DebugHelper::dump($throwable, $this->pretty);

        return $this->exit ? $throwable->getCode() :
            null;
    }
}