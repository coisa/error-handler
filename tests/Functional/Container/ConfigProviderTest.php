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
use Laminas\ServiceManager\ServiceManager;
use Nyholm\Psr7\Factory\Psr17Factory;
use Phly\EventDispatcher\EventDispatcher;
use Phly\EventDispatcher\ListenerProvider\ListenerProviderAggregate;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @var ServiceManager
     */
    private $serviceManager;

    /**
     * @var ConfigProvider
     */
    private $configProvider;

    /**
     * Creates isolated container and reporting collaborators for each test.
     */
    protected function setUp(): void
    {
        $this->configProvider = new ConfigProvider();
        $configs              = ($this->configProvider)();

        $this->serviceManager = new ServiceManager($configs['dependencies']);

        $this->setUpServiceManager($this->serviceManager);
    }

    /**
     * Supplies application-owned PSR-14 and PSR-17 implementations.
     *
     * @param ServiceManager $serviceManager Service Manager for the fixture.
     */
    public function setUpServiceManager(ServiceManager $serviceManager): void
    {
        $listenerProvider = new ListenerProviderAggregate();
        $eventDispatcher  = new EventDispatcher($listenerProvider);
        $factory          = new Psr17Factory();

        $serviceManager->setService(EventDispatcherInterface::class, $eventDispatcher);
        $serviceManager->setService(ResponseFactoryInterface::class, $factory);
        $serviceManager->setService(StreamFactoryInterface::class, $factory);
    }

    /**
     * Provides every service identifier declared by the Laminas adapter.
     *
     * @return list<array{class-string}> Independent service-identifier cases.
     */
    public static function provideContainerDependenciesClassNames(): array
    {
        $dependencies = (new ConfigProvider())->getDependencies();

        return array_chunk(array_keys(array_merge(...array_values($dependencies))), 1);
    }

    /**
     * Verifies Laminas advertises each configured service identifier.
     *
     * @param class-string $className The configured service identifier.
     */
    #[DataProvider('provideContainerDependenciesClassNames')]
    public function testServiceManagerHasConfigProviderDependencies(string $className): void
    {
        static::assertTrue($this->serviceManager->has($className));
    }

    /**
     * Provides service identifiers and their expected concrete types.
     *
     * @return list<array{class-string, class-string}> Service identifiers and expected types.
     */
    public static function provideContainerDependenciesWithInstanceType(): array
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
     * Verifies Laminas resolves each alias and factory to its expected type.
     *
     * @param class-string $className The configured service identifier.
     * @param class-string $instanceOf The expected resolved service type.
     */
    #[DataProvider('provideContainerDependenciesWithInstanceType')]
    public function testServiceManagerCanCreateDependecyThatImplements(string $className, string $instanceOf): void
    {
        $object = $this->serviceManager->get($className);
        static::assertInstanceOf($instanceOf, $object);
    }
}
