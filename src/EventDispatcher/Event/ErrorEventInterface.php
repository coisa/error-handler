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

namespace CoiSA\ErrorHandler\EventDispatcher\Event;

/**
 * Interface ErrorEventInterface
 *
 * @package CoiSA\ErrorHandler\EventDispatcher\Event
 */
interface ErrorEventInterface
{
    /**
     * @return string
     */
    public function __toString(): string;

    /**
     * @return \Throwable
     */
    public function getTrowable(): \Throwable;
}
