<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Helper
 * @since 2017-07-25
 */

namespace CoiSA\ErrorHandler\Helper;

use Throwable;

/**
 * Class ExceptionHelper
 */
class ExceptionHelper
{
    /**
     * @param Throwable $throwable
     * @return array
     */
    public static function toArray(Throwable $throwable): array
    {
        return [
            'class' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'code' => $throwable->getCode(),
            'file' => $throwable->getFile() . ':' . $throwable->getLine(),
        ];
    }

    /**
     * @param Throwable $throwable
     * @param int $options [optional]
     * @param int $depth [optional]
     * @return string
     */
    public static function toJson(Throwable $throwable, $options = 0, $depth = 512): string
    {
        return json_encode(self::toArray($throwable), $options, $depth);
    }
}