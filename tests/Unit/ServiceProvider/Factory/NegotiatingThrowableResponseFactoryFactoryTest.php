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

use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\ServiceProvider\Factory\NegotiatingThrowableResponseFactoryFactory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Verifies lazy container selection without resolving reporters, requests or unrelated renderers.
 *
 * @internal
 */
#[CoversClass(NegotiatingThrowableResponseFactoryFactory::class)]
#[UsesClass(NegotiatingThrowableResponseFactory::class)]
final class NegotiatingThrowableResponseFactoryFactoryTest extends TestCase
{
    /**
     * Resolves only the selected representation and forwards the exact invocation context.
     *
     * @param string       $accept      the current Accept header
     * @param class-string $selected    the concrete renderer selected by the provider map
     * @param string       $contentType the selected representation or untouched fallback type
     */
    #[DataProvider('provideOnlyTheSelectedRendererIsResolvedCases')]
    public function testOnlyTheSelectedRendererIsResolved(
        string $accept,
        string $selected,
        string $contentType,
    ): void {
        $request = new ServerRequest('GET', 'https://example.test/', ['Accept' => $accept]);
        $original = new \RuntimeException('Synthetic failure', 503);
        $chosen = $this->createMock(ThrowableResponseFactoryInterface::class);
        $chosen->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($original), self::identicalTo($request))
            ->willReturn(new Response(503, ['Content-Type' => 'application/custom'], 'Selected renderer'))
        ;
        $fallback = $chosen;
        if (ThrowableResponseFactory::class !== $selected) {
            $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
            $fallback->expects(self::never())->method('createResponseFromThrowable');
        }
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(ThrowableResponseFactory::class === $selected ? 1 : 2))
            ->method('get')->willReturnMap([
                [ThrowableResponseFactory::class, $fallback],
                [$selected, $chosen],
            ])
        ;
        $container->expects(self::never())->method('has');
        $factory = (new NegotiatingThrowableResponseFactoryFactory())($container);

        $response = $factory->createResponseFromThrowable($original, $request);

        self::assertSame('Selected renderer', (string) $response->getBody());
        self::assertSame(503, $response->getStatusCode());
        self::assertSame($contentType, $response->getHeaderLine('Content-Type'));
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    /** @return iterable<string, array{string, class-string, string}> Public representation and fallback choices. */
    public static function provideOnlyTheSelectedRendererIsResolvedCases(): iterable
    {
        yield 'missing header' => ['', ThrowableResponseFactory::class, 'application/custom'];

        yield 'unsupported header' => ['image/png', ThrowableResponseFactory::class, 'application/custom'];

        yield 'plain text' => ['text/plain', ThrowableResponseFactory::class, 'text/plain'];

        yield 'wildcard uses server priority' => ['*/*', ThrowableResponseFactory::class, 'text/plain'];

        yield 'JSON' => ['application/json', JsonThrowableResponseFactory::class, 'application/json'];

        yield 'Problem Details' => ['application/problem+json', JsonThrowableResponseFactory::class,
            'application/problem+json'];

        yield 'HTML' => ['text/html', HtmlThrowableResponseFactory::class, 'text/html; charset=UTF-8'];

        yield 'explicit HTML charset' => [
            'text/html; charset=UTF-8', HtmlThrowableResponseFactory::class, 'text/html; charset=UTF-8',
        ];

        yield 'case-insensitive HTML charset' => [
            'TEXT/HTML; CHARSET=utf-8', HtmlThrowableResponseFactory::class, 'text/html; charset=UTF-8',
        ];

        yield 'actual HTML charset excluded despite broader permission' => [
            'text/html; charset=utf-8;q=0, text/html;q=1, application/json;q=0.5',
            JsonThrowableResponseFactory::class,
            'application/json',
        ];

        yield 'actual HTML charset preferred over broader exclusion' => [
            'text/html; charset=UTF-8;q=1, text/html;q=0, application/json;q=0.5',
            HtmlThrowableResponseFactory::class,
            'text/html; charset=UTF-8',
        ];

        yield 'parameter-specific HTML quality remains below JSON' => [
            'text/html; charset=UTF-8;q=0.4, text/html;q=1, application/json;q=0.5',
            JsonThrowableResponseFactory::class,
            'application/json',
        ];

        yield 'unavailable HTML charset falls back' => [
            'text/html; charset=ISO-8859-1', ThrowableResponseFactory::class, 'application/custom',
        ];

        yield 'excluding another HTML charset still permits UTF-8' => [
            'text/html; charset=ISO-8859-1;q=0, text/html;q=1',
            HtmlThrowableResponseFactory::class,
            'text/html; charset=UTF-8',
        ];
    }

    /** Verifies a shared factory resolves each invocation's representation without retaining a request. */
    public function testSequentialRequestsResolveOnlyTheirCurrentRepresentations(): void
    {
        $original = new \RuntimeException('Sequential failure');
        $jsonRequest = new ServerRequest('GET', '/', ['Accept' => 'application/json']);
        $htmlRequest = new ServerRequest('GET', '/', ['Accept' => 'text/html']);
        $json = $this->createMock(ThrowableResponseFactoryInterface::class);
        $json->expects(self::exactly(2))->method('createResponseFromThrowable')
            ->with(self::identicalTo($original), self::identicalTo($jsonRequest))->willReturn(new Response())
        ;
        $html = $this->createMock(ThrowableResponseFactoryInterface::class);
        $html->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($original), self::identicalTo($htmlRequest))->willReturn(new Response())
        ;
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $ids = [];
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(4))->method('get')->willReturnCallback(
            /**
             * Records the exact lazy service-resolution order.
             *
             * @param string $id the requested concrete renderer
             *
             * @return ThrowableResponseFactoryInterface the configured renderer
             */
            static function (string $id) use (&$ids, $fallback, $json, $html): ThrowableResponseFactoryInterface {
                $ids[] = $id;

                return match ($id) {
                    ThrowableResponseFactory::class => $fallback,
                    JsonThrowableResponseFactory::class => $json,
                    HtmlThrowableResponseFactory::class => $html,
                    default => self::fail('Unexpected service: ' . $id),
                };
            },
        );
        $container->expects(self::never())->method('has');
        $factory = (new NegotiatingThrowableResponseFactoryFactory())($container);
        self::assertSame([ThrowableResponseFactory::class], $ids);

        $factory->createResponseFromThrowable($original, $jsonRequest);
        $factory->createResponseFromThrowable($original, $htmlRequest);
        $factory->createResponseFromThrowable($original, $jsonRequest);

        self::assertSame([
            ThrowableResponseFactory::class,
            JsonThrowableResponseFactory::class,
            HtmlThrowableResponseFactory::class,
            JsonThrowableResponseFactory::class,
        ], $ids);
    }

    /**
     * Rejects incompatible application definitions without silently rendering a different representation.
     *
     * @param class-string $invalid the service whose application definition is invalid
     */
    #[DataProvider('provideInvalidDefinitions')]
    public function testInvalidDefinitionsFailWithoutFallback(string $invalid): void
    {
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $fallbackDefinition = ThrowableResponseFactory::class === $invalid ? new \stdClass() : $fallback;
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(ThrowableResponseFactory::class === $invalid ? 1 : 2))
            ->method('get')->willReturnMap([
                [ThrowableResponseFactory::class, $fallbackDefinition],
                [JsonThrowableResponseFactory::class, new \stdClass()],
            ])
        ;
        $container->expects(self::never())->method('has');
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage($invalid . ' must implement ' . ThrowableResponseFactoryInterface::class);
        $factory = (new NegotiatingThrowableResponseFactoryFactory())($container);
        $factory->createResponseFromThrowable(
            new \RuntimeException('Invalid definition'),
            new ServerRequest('GET', '/', ['Accept' => 'application/json']),
        );
    }

    /** @return iterable<string, array{class-string}> Required fallback and lazily selected invalid definitions. */
    public static function provideInvalidDefinitions(): iterable
    {
        yield 'required fallback' => [ThrowableResponseFactory::class];

        yield 'selected JSON' => [JsonThrowableResponseFactory::class];
    }

    /**
     * Preserves the exact failure from required and lazily selected services.
     *
     * @param class-string $failed the renderer service whose construction fails
     */
    #[DataProvider('provideInvalidDefinitions')]
    public function testConstructionFailuresRemainIdentical(string $failed): void
    {
        $failure = new \LogicException('Configured renderer failed');
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $container = $this->createMock(ContainerInterface::class);
        $container->expects(self::exactly(ThrowableResponseFactory::class === $failed ? 1 : 2))
            ->method('get')->willReturnCallback(
                /**
                 * Fails only the explicitly selected service, preserving its original cause.
                 *
                 * @param string $id the requested renderer service
                 *
                 * @return ThrowableResponseFactoryInterface the nonfailing fallback
                 *
                 * @throws \LogicException the exact configured failure
                 */
                static function (string $id) use ($failed, $failure, $fallback): ThrowableResponseFactoryInterface {
                    if ($id === $failed) {
                        throw $failure;
                    }
                    self::assertSame(ThrowableResponseFactory::class, $id);

                    return $fallback;
                },
            )
        ;
        $container->expects(self::never())->method('has');

        try {
            $factory = (new NegotiatingThrowableResponseFactoryFactory())($container);
            $factory->createResponseFromThrowable(
                new \RuntimeException('Original failure'),
                new ServerRequest('GET', '/', ['Accept' => 'application/json']),
            );
            self::fail('A configured renderer failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
