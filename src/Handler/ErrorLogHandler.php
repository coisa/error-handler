<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-24
 */

namespace CoiSA\ErrorHandler\Handler;

use CoiSA\ErrorHandler\Helper\ExceptionHelper;

/**
 * Class ErrorLogHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class ErrorLogHandler implements HandlerInterface
{
    /** @var array */
    private $options;

    /**
     * ErrorLogHandler constructor.
     *
     * @param int $message_type [optional]
     * @param string $destination [optional]
     * @param string $extra_headers [optional]
     */
    public function __construct(int $message_type = null, string $destination = null, string $extra_headers = null)
    {
        $this->options = compact('message_type', 'destination', 'extra_headers');
    }

    /**
     * @param \Throwable $throwable
     * @return int
     */
    public function __invoke(\Throwable $throwable): ?int
    {
        error_log(
            ExceptionHelper::toJson($throwable),
            $this->options['message_type'],
            $this->options['destination'],
            $this->options['extra_headers']
        );

        return null;
    }
}