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

    /**
     * @param string $dump
     * @return string
     */
    public static function pre(string $dump): string
    {
        return "<pre>{$dump}</pre>";
    }

    /**
     * @param Throwable $throwable
     * @param bool $pre
     * @return string
     */
    public static function export(Throwable $throwable, bool $pre = false): string
    {
        $exported = var_export($throwable, true);

        return $pre ? self::pre($exported) :
            $exported;
    }

    /**
     * @param Throwable $throwable
     * @param bool $pre
     * @return string
     */
    public static function dump(Throwable $throwable, bool $pre = false): string
    {
        ob_start();
        try {
            var_dump($throwable);
        } catch (\Throwable $exception) {
            // DO NOTHING
        }

        $dump = ob_get_clean();

        return $pre ? self::pre($dump) :
            $dump;
    }
}