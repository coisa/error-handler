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

use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use CoiSA\ErrorHandler\Container\Factory\AliasFactory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use Psr\Container\ContainerInterface;

require $argv[1] ?? dirname(__DIR__, 2) . '/vendor/autoload.php';

$values = [null, false, true, 0, 42, 0.5, '', 'service value', [], ['service' => true], new stdClass()];

foreach ($values as $value) {
    $container = new class ($value) implements ContainerInterface {
        /** @var mixed The exact value delegated by the synthetic container. */
        private $value;

        /** @param mixed $value the synthetic service value */
        public function __construct($value)
        {
            $this->value = $value;
        }

        /** @return mixed the original value for the expected opaque identifier */
        public function get(string $id)
        {
            if ('fixture-service' !== $id) {
                throw new RuntimeException('AliasFactory changed the delegated service identifier.');
            }

            return $this->value;
        }

        /** Report whether this synthetic container owns the identifier. */
        public function has(string $id): bool
        {
            return 'fixture-service' === $id;
        }
    };

    if ((new AliasFactory('fixture-service'))($container) !== $value) {
        throw new RuntimeException('AliasFactory must preserve exact service values and object identity.');
    }
}

$failure = new RuntimeException('Synthetic delegate failure.');
$throwingContainer = new class ($failure) implements ContainerInterface {
    /** @var RuntimeException The exact delegate failure. */
    private RuntimeException $failure;

    /** Store the synthetic failure thrown by lookup. */
    public function __construct(RuntimeException $failure)
    {
        $this->failure = $failure;
    }

    /** @return mixed lookup always throws the original synthetic failure */
    public function get(string $id)
    {
        throw $this->failure;
    }

    /** This fixture owns the entry whose construction fails. */
    public function has(string $id): bool
    {
        return true;
    }
};

try {
    (new AliasFactory('fixture-service'))($throwingContainer);

    throw new LogicException('AliasFactory must propagate delegate failures.');
} catch (RuntimeException $exception) {
    if ($exception !== $failure) {
        throw new LogicException('AliasFactory replaced the original delegate failure.');
    }
}

$legacy = new ErrorHandlerContainer();
$aggregate = $legacy->get(ThrowableHandlerInterface::class);
$handler = $legacy->get(ErrorHandler::class);

if (!$aggregate instanceof ThrowableHandlerAggregate || !$handler instanceof ErrorHandler) {
    throw new RuntimeException('Legacy aliases and the core factory must resolve on the declared runtime.');
}

if ($legacy->get(ThrowableHandlerInterface::class) !== $aggregate || $legacy->get(ErrorHandler::class) !== $handler) {
    throw new RuntimeException('Legacy alias and handler caching must remain unchanged.');
}

echo 'AliasFactory values, failures and legacy composition passed on PHP ' . PHP_VERSION . PHP_EOL;
