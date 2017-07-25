<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Helper
 * @since 2017-07-25
 */

namespace CoiSA\ErrorHandler\Helper;

use Throwable;

/**
 * Class DebugHelper
 */
class DebugHelper
{
    /**
     * @param string $dump
     * @return string
     */
    public static function pre(string $dump): string
    {
        return "<pre>{$dump}</pre>";
    }

    /**
     * @param string $dump
     * @return string
     */
    public static function pretty(string $dump)
    {
        // @TODO highlight code

        return self::pre($dump);
    }

    /**
     * @param mixed $mixed
     * @param bool $pretty
     * @return string
     */
    public static function print_r($mixed, bool $pretty = true): string
    {
        $print = print_r($mixed, true);

        return $pretty ? self::pre($print) :
            $print;
    }

    /**
     * @param mixed $expression
     * @param bool $pretty
     * @return string
     */
    public static function export($expression, bool $pretty = true): string
    {
        $exported = var_export($expression, true);

        return $pretty ? self::pre($exported) :
            $exported;
    }

    /**
     * @param mixed $expression
     * @param bool $pretty
     * @return string
     */
    public static function dump($expression, bool $pretty = true): string
    {
        ob_start();
        try {
            var_dump($expression);
        } catch (\Throwable $exception) {
            // DO NOTHING
        }

        $dump = ob_get_clean();

        return $pretty ? self::pre($dump) :
            $dump;
    }
}