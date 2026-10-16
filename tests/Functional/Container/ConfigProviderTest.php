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
 *
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

    /**
     * Compose fresh configured containers for each integration case.
     */
    protected function setUp(): void
    {
        $this->configProvider = new ConfigProvider();
        $configs              = ($this->configProvider)();

        $this->serviceManager = new ServiceManager($configs['dependencies']);
        $this->container      = new ErrorHandlerContainer($this->serviceManager);

        $this->setUpServiceManager($this->serviceManager);
    }

    /**
     * Supply application-owned event dispatching and HTTP factories.
     *
     * @param ServiceManager $serviceManager the container receiving fixture services
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
     * Verify the configured ServiceManager advertises each library service.
     *
     * @param class-string $className the configured service identifier
     *
     * @dataProvider provideContainerDependenciesClassNames
     */
    public function testServiceManagerHasConfigProviderDependencies(string $className): void
    {
        self::assertTrue($this->serviceManager->has($className));
    }

    /**
     * Verify the wrapper advertises services supplied by its parent container.
     *
     * @param class-string $className the configured service identifier
     *
     * @dataProvider provideContainerDependenciesClassNames
     */
    public function testContainerWithServiceManagerHasConfigProviderDependencies(string $className): void
    {
        self::assertTrue($this->container->has($className));
    }

    /**
     * Verify a standalone library container advertises its own services.
     *
     * @param class-string $className the configured service identifier
     *
     * @dataProvider provideContainerDependenciesClassNames
     */
    public function testContainerWithoutServiceManagerHasConfigProviderDependencies(string $className): void
    {
        $container = new ErrorHandlerContainer();
        self::assertTrue($container->has($className));
    }

    /**
     * List every alias and factory service advertised by the provider.
     *
     * @return list<non-empty-array<int, class-string>> independent service identifiers
     */
    public static function provideContainerDependenciesClassNames(): iterable
    {
        $dependencies = (new ConfigProvider())->getDependencies();

        return array_chunk(array_keys(array_merge(...array_values($dependencies))), 1);
    }

    /**
     * Verify the configured ServiceManager creates the expected instance.
     *
     * @param class-string $className  the configured service identifier
     * @param class-string $instanceOf the expected concrete type or interface
     *
     * @dataProvider provideContainerDependenciesWithInstanceType
     */
    public function testServiceManagerCanCreateDependecyThatImplements(string $className, string $instanceOf): void
    {
        $object = $this->serviceManager->get($className);
        self::assertInstanceOf($instanceOf, $object);
    }

    /**
     * Verify the wrapper delegates or constructs the expected instance.
     *
     * @param class-string $className  the configured service identifier
     * @param class-string $instanceOf the expected concrete type or interface
     *
     * @dataProvider provideContainerDependenciesWithInstanceType
     */
    public function testContainerCanCreateDependecyThatImplements(string $className, string $instanceOf): void
    {
        $object = $this->container->get($className);
        self::assertInstanceOf($instanceOf, $object);
    }

    /**
     * Verify the configured wrapper still resolves after preparing another container.
     *
     * @param class-string $className  the configured service identifier
     * @param class-string $instanceOf the expected concrete type or interface
     *
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
        self::assertInstanceOf($instanceOf, $object);
    }

    /**
     * Pair each configured service identifier with its expected instance type.
     *
     * @return list<array{class-string, class-string}> service and expected type pairs
     */
    public static function provideContainerDependenciesWithInstanceType(): iterable
    {
        $dependencies = (new ConfigProvider())->getDependencies();
        $classNames   = array_keys(array_merge(...array_values($dependencies)));

        return array_map(
            /**
             * Resolve an alias's expected type without constructing its service.
             *
             * @param class-string $classNameName the configured service identifier
             *
             * @return array{class-string, class-string} service and expected type
             */
            static function ($classNameName) use ($dependencies) {
                $instanceOf = $dependencies['aliases'][$classNameName] ?? $classNameName;

                return [
                    $classNameName,
                    $instanceOf,
                ];
            },
            $classNames,
        );
    }
}
