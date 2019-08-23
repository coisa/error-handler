<?php

/**
 * This file is part of coisa/error-handler.
 *
 * (c) Felipe Sayão Lobato Abreu <github@felipeabreu.com.br>
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\EventDispatcher\ListenerProvider;

use CoiSA\ErrorHandler\EventDispatcher\Listener\LogErrorEventListener;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * Class ErrorEventListenerProvider
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\ListenerProvider
 */
final class ErrorEventListenerProvider implements ListenerProviderInterface
{
    /**
     * @var LogErrorEventListener
     */
    private $logErrorEventListener;

    /**
     * ErrorEventListenerProvider constructor.
     *
     * @param LogErrorEventListener $logErrorEventListener
     */
    public function __construct(
        LogErrorEventListener $logErrorEventListener
    ) {
        $this->logErrorEventListener = $logErrorEventListener;
    }

    /**
     * @param object $event
     *
     * @return iterable
     */
    public function getListenersForEvent(object $event): iterable
    {
        yield $this->logErrorEventListener;
    }
}
