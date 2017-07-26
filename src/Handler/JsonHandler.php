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
    /** @const int json_encode default options */
    const DEFAULT_OPTIONS =
        JSON_FORCE_OBJECT |
        JSON_PRETTY_PRINT |
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE |
        JSON_PARTIAL_OUTPUT_ON_ERROR |
        JSON_PRESERVE_ZERO_FRACTION;

    /** @var int json_encode options */
    private $options;

    /** @var int json_encode depth */
    private $depth;

    /**
     * JsonHandler constructor.
     *
     * @param int $options
     * @param int $depth optional
     */
    public function __construct(int $options = self::DEFAULT_OPTIONS, int $depth = 512)
    {
        $this->options = $options;
        $this->depth = $depth;
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

        echo ExceptionHelper::toJson($throwable, $this->options, $this->depth);

        return $throwable->getCode();
    }
}