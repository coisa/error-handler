<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

use CoiSA\ErrorHandler\Helper\DebugHelper;

/**
 * Class VarExportHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class VarExportHandler extends DebugHandler
{
    /**
     * @param \Throwable $throwable
     * @return int
     */
    public function __invoke(\Throwable $throwable): ?int
    {
        echo DebugHelper::export($throwable, $this->pretty);

        return $this->exit ? $throwable->getCode() :
            null;
    }
}