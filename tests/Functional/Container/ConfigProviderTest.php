<?php

declare(strict_types=1);

/**
 * This file is part of coisa/error-handler.
 *
 * This source file is subject to the license that is bundled
 * with this source code in the file LICENSE.
 *
 * @link      https://github.com/coisa/error-handler
 *
 * @copyright Copyright (c) 2022-2024 Felipe Sayão Lobato Abreu <github@mentordosnerds.com.br>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace CoiSA\ErrorHandler\Test\Functional\Container;

use CoiSA\ErrorHandler\Container\ConfigProvider;
use CoiSA\ErrorHandler\Container\ErrorHandlerContainer;
use Laminas\ServiceManager\ServiceManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Class ConfigProviderTest.
 *
 * @package CoiSA\ErrorHandler\Test\Functional\Container
 *
 * @internal
 * @coversNothing
 */
final class ConfigProviderTest extends TestCase
{
    /** @var ServiceManager */
    private $serviceManager;

    /** @var ErrorHandlerContainer */
    private $container;

    /** @var ConfigProvider */
    private $configProvider;

    protected function setUp(): void
    {
        $this->configProvider = new ConfigProvider();
        $configs              = ($this->configProvider)();

        $this->serviceManager = new ServiceManager($configs['dependencies']);
        $this->container      = new ErrorHandlerContainer($this->serviceManager);

        $this->setUpServiceManager($this->serviceManager);
    }

    public function setUpServiceManager(ServiceManager $serviceManager): void
    {
        $listenerProvider = new ListenerProviderAggregate();
        $eventDispatcher  = new EventDispatcher($listenerProvider);
        $factory          = new Psr17Factory();

        $serviceManager->setService(EventDispatcherInterface::class, $eventDispatcher);
        $serviceManager->setService(ResponseFactoryInterface::class, $factory);
        $serviceManager->setService(StreamFactoryInterface::class, $factory);
    }

    public function provideContainerDependenciesClassNames(): array
    {
        $dependencies = (new ConfigProvider())->getDependencies();

        return array_chunk(array_keys(array_merge(...array_values($dependencies))), 1);
    }

    /** @dataProvider provideContainerDependenciesClassNames */
    public function testServiceManagerHasConfigProviderDependencies(string $className): void
    {
        static::assertTrue($this->serviceManager->has($className));
    }

    /** @dataProvider provideContainerDependenciesClassNames */
    public function testContainerWithServiceManagerHasConfigProviderDependencies(string $className): void
    {
        static::assertTrue($this->container->has($className));
    }

    /** @dataProvider provideContainerDependenciesClassNames */
    public function testContainerWithoutServiceManagerHasConfigProviderDependencies(string $className): void
    {
        $container = new ErrorHandlerContainer();
        static::assertTrue($container->has($className));
    }

    public function provideContainerDependenciesWithInstanceType(): array
    {
        $dependencies = (new ConfigProvider())->getDependencies();
        $classNames   = array_keys(array_merge(...array_values($dependencies)));

        return array_map(function ($classNameName) use ($dependencies) {
            $instanceOf = $dependencies['aliases'][$classNameName] ?? $classNameName;

            return [
                $classNameName,
                $instanceOf,
            ];
        }, $classNames);
    }

    /**
     * @dataProvider provideContainerDependenciesWithInstanceType
     */
    public function testServiceManagerCanCreateDependecyThatImplements(string $className, string $instanceOf): void
    {
        $object = $this->serviceManager->get($className);
        static::assertInstanceOf($instanceOf, $object);
    }

    /**
     * @dataProvider provideContainerDependenciesWithInstanceType
     */
    public function testContainerCanCreateDependecyThatImplements(string $className, string $instanceOf): void
    {
        $object = $this->container->get($className);
        static::assertInstanceOf($instanceOf, $object);
    }

    /**
     * @dataProvider provideContainerDependenciesWithInstanceType
     */
    public function testWithCleanServiceManagerCanCreateDependecyThatImplements(
        string $className,
        string $instanceOf
    ): void {
        $serviceManager = new ServiceManager();
        $container      = new ErrorHandlerContainer($this->serviceManager);

        $this->setUpServiceManager($serviceManager);

        $object = $container->get($className);
        static::assertInstanceOf($instanceOf, $object);
    }
}
