<?php

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2026 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

declare(strict_types=1);

namespace CoiSA\ErrorHandler\Handler;

/**
 * Forwards selected throwables to an explicitly configured reporter.
 *
 * A nonmatch skips only this delegate; it does not recover the failed operation.
 * Predicate and delegate failures propagate without retry or recursive reporting.
 */
final class FilteringThrowableHandler implements ThrowableHandlerInterface
{
    /** @var ThrowableHandlerInterface the reporter called on a match */
    private ThrowableHandlerInterface $throwableHandler;

    /** @var callable(\Throwable): bool the application-owned inclusion predicate */
    private $predicate;

    /**
     * Store the explicit delegate and required inclusion policy without registration.
     *
     * @param ThrowableHandlerInterface  $throwableHandler the reporter invoked on a match
     * @param callable(\Throwable): bool $predicate        true forwards; false skips the delegate
     */
    public function __construct(ThrowableHandlerInterface $throwableHandler, callable $predicate)
    {
        $this->throwableHandler = $throwableHandler;
        $this->predicate = $predicate;
    }

    /**
     * Evaluate the predicate once and forward the original throwable on a match.
     *
     * @param \Throwable $throwable the original failure to select and report
     *
     * @throws \Throwable when the predicate or delegate fails, including a non-boolean result
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        if ($this->matches($throwable)) {
            $this->throwableHandler->handleThrowable($throwable);
        }
    }

    /**
     * Enforce a boolean result at a strict PHP return boundary.
     *
     * @param \Throwable $throwable the unchanged predicate input
     *
     * @return bool whether this invocation should reach the delegate
     *
     * @throws \Throwable when the predicate fails or returns a non-boolean value
     */
    private function matches(\Throwable $throwable): bool
    {
        return ($this->predicate)($throwable);
    }
}
