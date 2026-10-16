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

namespace CoiSA\ErrorHandler\Test\Unit\Container;

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Container\Factory;
use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerAggregate;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the public configuration has consistent usable factories and aliases.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Container\ConfigProvider
 */
final class ConfigProviderTest extends TestCase
{
    /** Verifies each alias targets a declared service and every factory can be invoked. */
    public function testConfigurationKeepsAliasesAndFactoriesConsistent(): void
    {
        $provider = new ConfigProvider();
        $dependencies = $provider->getDependencies();
        self::assertSame(['dependencies' => $dependencies], $provider());
        self::assertSame($provider->getAliases(), $dependencies['aliases']);
        self::assertSame($provider->getFactories(), $dependencies['factories']);
        self::assertSame(ThrowableHandlerAggregate::class, $dependencies['aliases'][ThrowableHandlerInterface::class]);
        self::assertSame(Factory\ErrorHandlerFactory::class, $dependencies['factories'][ErrorHandler::class]);
        foreach ($dependencies['aliases'] as $target) {
            self::assertArrayHasKey($target, $dependencies['factories']);
        }
        foreach ($dependencies['factories'] as $factory) {
            self::assertIsCallable(new $factory());
        }
    }
}
