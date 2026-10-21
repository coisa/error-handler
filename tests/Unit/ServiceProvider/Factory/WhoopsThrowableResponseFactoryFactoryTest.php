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

namespace CoiSA\ErrorHandler\Test\Unit\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use CoiSA\ErrorHandler\ServiceProvider\Factory\WhoopsThrowableResponseFactoryFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\Handler;
use Whoops\Handler\PrettyPageHandler;

/**
 * Verifies explicit Whoops composition defers application-owned pages until rendering.
 *
 * @internal
 */
#[CoversClass(WhoopsThrowableResponseFactoryFactory::class)]
#[UsesClass(WhoopsThrowableResponseFactory::class)]
final class WhoopsThrowableResponseFactoryFactoryTest extends TestCase
{
    /** Verifies the configured page is used for each sequential render without reading ambient diagnostics. */
    public function testPageResolutionIsLazyAndRetainsApplicationConfiguration(): void
    {
        $messages = new Psr17Factory();
        $page = $this->createMock(PrettyPageHandler::class);
        $page->expects(self::exactly(2))->method('handle')->willReturn(Handler::DONE);
        $ids = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('get')->willReturnCallback(
            /**
             * Returns the application page only when the renderer invokes its callback.
             *
             * @param string $id the requested HTTP or page service
             *
             * @return PrettyPageHandler|Psr17Factory the configured service
             */
            static function (string $id) use (&$ids, $messages, $page): Psr17Factory|PrettyPageHandler {
                $ids[] = $id;

                return match ($id) {
                    ResponseFactoryInterface::class, StreamFactoryInterface::class => $messages,
                    PrettyPageHandler::class => $page,
                    default => self::fail('Unexpected service: ' . $id),
                };
            },
        );
        $container->expects(self::never())->method('has');
        $factory = (new WhoopsThrowableResponseFactoryFactory())($container);
        self::assertSame([ResponseFactoryInterface::class, StreamFactoryInterface::class], $ids);
        $request = new ServerRequest('GET', 'https://example.test/');
        $this->expectOutputString('');

        foreach ([404, 503] as $status) {
            $response = $factory->createResponseFromThrowable(
                new \RuntimeException('Synthetic page', $status),
                $request,
            );
            self::assertSame($status, $response->getStatusCode());
            self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        }

        self::assertSame([
            ResponseFactoryInterface::class,
            StreamFactoryInterface::class,
            PrettyPageHandler::class,
            PrettyPageHandler::class,
        ], $ids);
    }

    /** Verifies page-resolution failures are lazy and propagate without replacing the application page. */
    public function testPageResolutionFailureRemainsIdentical(): void
    {
        $messages = new Psr17Factory();
        $failure = new \LogicException('Configured page unavailable');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(3))->method('get')->willReturnCallback(
            /**
             * Throws only when the lazy page service is requested.
             *
             * @param string $id the requested HTTP or page service
             *
             * @return Psr17Factory the application HTTP factories
             *
             * @throws \LogicException the exact application page-construction failure
             */
            static function (string $id) use ($messages, $failure): Psr17Factory {
                if (PrettyPageHandler::class === $id) {
                    throw $failure;
                }
                self::assertContains($id, [ResponseFactoryInterface::class, StreamFactoryInterface::class]);

                return $messages;
            },
        );
        $container->expects(self::never())->method('has');
        $factory = (new WhoopsThrowableResponseFactoryFactory())($container);

        try {
            $factory->createResponseFromThrowable(new \RuntimeException(), new ServerRequest('GET', '/'));
            self::fail('The configured page-construction failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /**
     * Preserves required PSR-17 dependency failures before any page is requested.
     *
     * @param class-string $failed the HTTP factory service whose construction fails
     */
    #[DataProvider('provideHttpFactoryFailuresRemainIdenticalCases')]
    public function testHttpFactoryFailuresRemainIdentical(string $failed): void
    {
        $messages = new Psr17Factory();
        $failure = new \LogicException('HTTP factory unavailable');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(ResponseFactoryInterface::class === $failed ? 1 : 2))
            ->method('get')->willReturnCallback(
                /**
                 * Resolves only HTTP prerequisites, failing the selected one.
                 *
                 * @param string $id the required HTTP factory service
                 *
                 * @return Psr17Factory a successfully configured HTTP factory
                 *
                 * @throws \LogicException the exact construction failure
                 */
                static function (string $id) use ($failed, $messages, $failure): Psr17Factory {
                    if ($id === $failed) {
                        throw $failure;
                    }
                    self::assertSame(ResponseFactoryInterface::class, $id);

                    return $messages;
                },
            )
        ;
        $container->expects(self::never())->method('has');

        try {
            (new WhoopsThrowableResponseFactoryFactory())($container);
            self::fail('The HTTP factory failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** @return iterable<string, array{class-string}> Required HTTP factory service IDs. */
    public static function provideHttpFactoryFailuresRemainIdenticalCases(): iterable
    {
        yield 'response' => [ResponseFactoryInterface::class];

        yield 'stream' => [StreamFactoryInterface::class];
    }
}
