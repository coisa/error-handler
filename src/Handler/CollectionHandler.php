<?php
/**
 * @author Felipe Sayão Lobato Abreu <contato@felipeabreu.com.br>
 * @package CoiSA\ErrorHandler\Handler
 * @since 2017-07-25
 */

namespace CoiSA\ErrorHandler\Handler;

/**
 * Class CollectionHandler
 * @package CoiSA\ErrorHandler\Handler
 */
class CollectionHandler implements HandlerInterface
{
    /**
     * @var HandlerInterface[]
     */
    private $handlers;

    /**
     * HandlerAggregate constructor.
     *
     * @param HandlerInterface[] ...$handlers
     */
    public function __construct(HandlerInterface ...$handlers)
    {
        $this->handlers = $handlers;
    }

    /**
     * Execute cascade handlers
     *
     * @param \Throwable $exception
     * @return int|null
     */
    public function __invoke(\Throwable $exception): ?int
    {
        foreach ($this->handlers as $handler) {
            $signal = $handler($exception);

            if ($signal) {
                return $signal;
            }
        }
    }
}