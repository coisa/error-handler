<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

use CoiSA\ErrorHandler\Helper\ExceptionHelper;

/**
 * Class JsonHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class JsonHandler implements HandlerInterface
{
    /** @var int json_encode options */
    private $options;

    /**
     * JsonHandler constructor.
     *
     * @param int $options [optional]
     */
    public function __construct(int $options = null)
    {
        $this->options = $options;
    }

    /**
     * @param \Throwable $throwable
     * @return int|null
     */
    public function __invoke(\Throwable $throwable): ?int
    {
        if (!headers_sent()) {
            header_remove('Content-Type');
            header_remove('Content-Length');

            header('Content-Type: application/json;charset=utf-8');
        }

        echo ExceptionHelper::toJson($throwable, $this->options);

        return $throwable->getCode();
    }
}