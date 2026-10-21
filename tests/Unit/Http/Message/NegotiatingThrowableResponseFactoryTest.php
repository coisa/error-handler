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

namespace CoiSA\ErrorHandler\Test\Unit\Http\Message;

use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies real Accept matching, per-request selection and pure lazy renderer delegation.
 *
 * @internal
 */
#[CoversClass(NegotiatingThrowableResponseFactory::class)]
final class NegotiatingThrowableResponseFactoryTest extends TestCase
{
    /**
     * Uses the real Symfony matcher, including explicit exclusions behind wildcard ranges.
     *
     * @param string       $accept   the current client preference
     * @param string       $selected the expected representation
     * @param list<string> $created  only the winning lazy callback, or none for fallback
     */
    #[DataProvider('providePreferencesCases')]
    public function testPreferences(string $accept, string $selected, array $created): void
    {
        $messages = new Psr17Factory();
        $request = $messages->createServerRequest('GET', '/synthetic')->withHeader('Accept', $accept);
        $throwable = new \RuntimeException('Private diagnostic', 503);
        $factories = [];
        $renderers = [];
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $types = ['text/plain' => 'plain', 'application/json' => 'json', 'text/html' => 'html'];

        foreach ($types as $type => $name) {
            $renderer = 'plain' === $name ? $fallback : $this->createMock(ThrowableResponseFactoryInterface::class);
            $response = $messages->createResponse(503)->withBody($messages->createStream($name));
            if ('plain' !== $name) {
                $response = $response->withHeader('Content-Type', $type);
            }
            $renderer->expects($name === $selected ? self::once() : self::never())
                ->method('createResponseFromThrowable')->with($throwable, $request)->willReturn($response)
            ;
            $renderers[$type]
                // @return ThrowableResponseFactoryInterface the selected application renderer
                = static function () use (&$factories, $name, $renderer): ThrowableResponseFactoryInterface {
                    $factories[] = $name;

                    return $renderer;
                };
        }

        $factory = new NegotiatingThrowableResponseFactory($fallback, $renderers);
        self::assertSame([], $factories);
        $response = $factory->createResponseFromThrowable($throwable, $request);

        self::assertSame($created, $factories);
        self::assertSame($selected, (string) $response->getBody());
        self::assertSame(array_search($selected, $types, true), $response->getHeaderLine('Content-Type'));
        self::assertSame(503, $response->getStatusCode());
        self::assertSame('Accept', $response->getHeaderLine('Vary'));
    }

    /** @return array<string, array{string, string, list<string>}> accepted and excluded client preferences */
    public static function providePreferencesCases(): iterable
    {
        return [
            'absent Accept uses generic fallback' => ['', 'plain', []],
            'unsupported type uses generic fallback' => ['application/xml', 'plain', []],
            'malformed media range cannot select a candidate' => ['not-a-media-type', 'plain', []],
            'explicit JSON' => ['application/json', 'json', ['json']],
            'explicit public HTML' => ['text/html', 'html', ['html']],
            'plain representation' => ['text/plain', 'plain', ['plain']],
            'browser preference' => ['text/html, application/xhtml+xml, */*;q=0.8', 'html', ['html']],
            'JSON higher quality' => ['text/html;q=0.6, application/json;q=0.9', 'json', ['json']],
            'HTML higher quality' => ['application/json;q=0.4, text/html;q=0.7', 'html', ['html']],
            'universal wildcard follows configured order' => ['*/*', 'plain', ['plain']],
            'subtype wildcard selects JSON' => ['application/*', 'json', ['json']],
            'equal quality follows configured order' => ['text/html, application/json', 'json', ['json']],
            'equal exact and wildcard qualities follow configured order' => ['text/html, */*', 'plain', ['plain']],
            'case-insensitive media type' => ['APPLICATION/JSON', 'json', ['json']],
            'HTML excluded despite universal wildcard' => ['text/html;q=0, */*;q=1', 'plain', ['plain']],
            'JSON excluded despite subtype wildcard' => ['application/json;q=0, application/*;q=1', 'plain', []],
            'plain excluded despite text wildcard' => ['text/plain;q=0, text/*;q=1', 'html', ['html']],
            'only zero quality cannot select JSON' => ['application/json;q=0', 'plain', []],
            'all concrete exclusions override wildcard' => [
                'text/plain;q=0, application/json;q=0, text/html;q=0, */*;q=1', 'plain', [],
            ],
        ];
    }

    /** Verifies parameter-specific precedence through the external matcher instead of a local parser. */
    public function testMediaParametersRetainSpecificExclusionsAndPreferences(): void
    {
        $messages = new Psr17Factory();
        $request = $messages->createServerRequest('GET', '/synthetic')->withHeader(
            'Accept',
            'text/html;q=0, text/html;charset=utf-8;q=0.9, application/json;q=0.7',
        );
        $throwable = new \RuntimeException('Synthetic diagnostic');
        $response = $messages->createResponse(500)->withHeader('Content-Type', 'text/html; charset=UTF-8');
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $html = $this->createMock(ThrowableResponseFactoryInterface::class);
        $html->expects(self::once())->method('createResponseFromThrowable')
            ->with($throwable, $request)->willReturn($response)
        ;
        $factory = new NegotiatingThrowableResponseFactory($fallback, [
            'text/html' => static function (): never {
                throw new \LogicException('Excluded plain HTML must not be selected.');
            },
            'application/json' => static function (): never {
                throw new \LogicException('Lower quality JSON must not be selected.');
            },
            'text/html; charset=UTF-8' => static fn(): ThrowableResponseFactoryInterface => $html,
        ]);

        $result = $factory->createResponseFromThrowable($throwable, $request);
        self::assertSame('text/html; charset=UTF-8', $result->getHeaderLine('Content-Type'));
    }

    /**
     * A selected alias controls representation metadata without changing its payload or cache policy.
     *
     * @param string      $selectedType the available representation accepted by the request
     * @param null|string $rendererType the renderer's original content type, when present
     * @param string      $expectedType the negotiated response type including retained parameters
     */
    #[DataProvider('provideSelectedMediaTypePreservesMatchingParametersCases')]
    public function testSelectedMediaTypePreservesMatchingParameters(
        string $selectedType,
        ?string $rendererType,
        string $expectedType,
    ): void {
        $messages = new Psr17Factory();
        $body = $messages->createStream('{"type":"about:blank","status":503}');
        $response = $messages->createResponse(503)->withBody($body)->withHeader('Cache-Control', 'no-store');
        if (null !== $rendererType) {
            $response = $response->withHeader('Content-Type', $rendererType);
        }
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
        $renderer->expects(self::once())->method('createResponseFromThrowable')->willReturn($response);
        $factory = new NegotiatingThrowableResponseFactory($fallback, [
            $selectedType => static fn(): ThrowableResponseFactoryInterface => $renderer,
        ]);
        $request = $messages->createServerRequest('GET', '/synthetic')->withHeader('Accept', $selectedType);

        $result = $factory->createResponseFromThrowable(new \RuntimeException(), $request);

        self::assertSame($expectedType, $result->getHeaderLine('Content-Type'));
        self::assertSame($body, $result->getBody());
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
        self::assertSame(503, $result->getStatusCode());
        self::assertSame($rendererType ?? '', $response->getHeaderLine('Content-Type'));
    }

    /** @return iterable<string, array{string, ?string, string}> aliases and explicit renderer parameters */
    public static function provideSelectedMediaTypePreservesMatchingParametersCases(): iterable
    {
        yield 'JSON alias' => ['application/json', 'application/problem+json', 'application/json'];

        yield 'canonical Problem Details' => [
            'application/problem+json', 'application/problem+json', 'application/problem+json',
        ];

        yield 'HTML retains charset' => ['text/html', 'text/html; charset=UTF-8', 'text/html; charset=UTF-8'];

        yield 'case-insensitive base retains charset' => [
            'text/html', 'TEXT/HTML; charset=UTF-8', 'TEXT/HTML; charset=UTF-8',
        ];

        yield 'configured parameters take precedence' => [
            'text/html; charset=UTF-8', 'text/html; charset=ISO-8859-1', 'text/html; charset=UTF-8',
        ];

        yield 'renderer omitted type' => ['application/json', null, 'application/json'];
    }

    /** The same cached factory reads and forwards every current request without caching a winner. */
    public function testSequentialRequestsRetainTheirOwnPreferencesAndIdentity(): void
    {
        $messages = new Psr17Factory();
        $received = [];
        $created = [];
        $renderers = [];
        foreach (['fallback', 'json', 'html'] as $name) {
            $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
            $renderer->expects(self::exactly('json' === $name ? 2 : 1))
                ->method('createResponseFromThrowable')->willReturnCallback(
                    /** @return ResponseInterface a response specific to this invocation */
                    static function (
                        \Throwable $throwable,
                        ServerRequestInterface $request,
                    ) use (
                        &$received,
                        $messages,
                        $name,
                    ): ResponseInterface {
                        $received[] = [$throwable, $request];

                        return $messages->createResponse(500)->withBody($messages->createStream($name));
                    },
                )
            ;
            $renderers[$name] = $renderer;
        }
        $lazy = [];
        foreach (['application/json' => 'json', 'text/html' => 'html'] as $type => $name) {
            // @return ThrowableResponseFactoryInterface the selected renderer for this invocation
            $lazy[$type] = static function () use (&$created, $renderers, $name): ThrowableResponseFactoryInterface {
                $created[] = $name;

                return $renderers[$name];
            };
        }
        $factory = new NegotiatingThrowableResponseFactory($renderers['fallback'], $lazy);
        $expected = [];
        $preferences = [
            'application/json' => 'json', 'text/html' => 'html', '' => 'fallback', 'application/*' => 'json',
        ];
        foreach ($preferences as $accept => $body) {
            $request = $messages->createServerRequest('GET', '/synthetic')->withHeader('Accept', $accept);
            $throwable = new \RuntimeException('Synthetic ' . $body);
            $expected[] = [$throwable, $request];
            self::assertSame($body, (string) $factory->createResponseFromThrowable($throwable, $request)->getBody());
        }
        self::assertSame($expected, $received);
        self::assertSame(['json', 'html', 'json'], $created);
    }

    /**
     * Preserves renderer cache policy and existing Vary fields without duplicate selecting fields.
     *
     * @param list<string> $existing the renderer's original Vary values
     * @param list<string> $expected the merged Vary values
     */
    #[DataProvider('provideVaryMergePreservesOtherResponseMetadataCases')]
    public function testVaryMergePreservesOtherResponseMetadata(array $existing, array $expected): void
    {
        $messages = new Psr17Factory();
        $response = $messages->createResponse(503)
            ->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store')
        ;
        if ([] !== $existing) {
            $response = $response->withHeader('Vary', $existing);
        }
        $fallback = self::createStub(ThrowableResponseFactoryInterface::class);
        $fallback->method('createResponseFromThrowable')->willReturn($response);
        $factory = new NegotiatingThrowableResponseFactory($fallback, []);
        $request = $messages->createServerRequest('HEAD', '/synthetic');
        $result = $factory->createResponseFromThrowable(new \RuntimeException(), $request);

        self::assertSame($expected, $result->getHeader('Vary'));
        self::assertSame($existing, $response->getHeader('Vary'));
        self::assertSame('application/json', $result->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $result->getHeaderLine('Cache-Control'));
        self::assertSame(503, $result->getStatusCode());
    }

    /** @return array<string, array{list<string>, list<string>}> selecting fields and wildcard policies */
    public static function provideVaryMergePreservesOtherResponseMetadataCases(): iterable
    {
        return [
            'absent' => [[], ['Accept']],
            'another field' => [['Origin'], ['Origin', 'Accept']],
            'comma-separated fields' => [['Origin, Accept-Encoding'], ['Origin, Accept-Encoding', 'Accept']],
            'existing case-insensitive Accept' => [['Origin', 'aCcEpT'], ['Origin', 'aCcEpT']],
            'existing comma-separated Accept' => [['Origin, aCcEpT'], ['Origin, aCcEpT']],
            'wildcard covers Accept' => [['*'], ['*']],
        ];
    }

    /** A JSON-only configuration cannot resolve an HTML renderer or change the JSON fallback's type. */
    public function testJsonOnlyConfigurationKeepsUnsupportedRequestsJson(): void
    {
        $messages = new Psr17Factory();
        $response = $messages->createResponse(500)->withHeader('Content-Type', 'application/json')
            ->withBody($messages->createStream('{"error":"Request failed"}'))
        ;
        $json = $this->createMock(ThrowableResponseFactoryInterface::class);
        $json->expects(self::exactly(3))->method('createResponseFromThrowable')->willReturn($response);
        $factory = new NegotiatingThrowableResponseFactory($json, [
            'application/json' => static fn(): ThrowableResponseFactoryInterface => $json,
        ]);
        foreach (['text/html', '', 'application/json'] as $accept) {
            $request = $messages->createServerRequest('GET', '/synthetic')->withHeader('Accept', $accept);
            $result = $factory->createResponseFromThrowable(new \RuntimeException(), $request);
            self::assertSame('application/json', $result->getHeaderLine('Content-Type'));
            self::assertSame('{"error":"Request failed"}', (string) $result->getBody());
        }
    }

    /** A selected lazy collaborator failure propagates instead of falling back or trying a later candidate. */
    public function testSelectedFactoryFailurePropagatesWithoutRetry(): void
    {
        $messages = new Psr17Factory();
        $failure = new \RuntimeException('Synthetic lazy construction failure');
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $factory = new NegotiatingThrowableResponseFactory($fallback, [
            'application/json' => static function () use ($failure): never {
                throw $failure;
            },
            'text/html' => static function (): never {
                throw new \LogicException('The lower quality callback must stay lazy.');
            },
        ]);
        $request = $messages->createServerRequest('GET', '/synthetic')
            ->withHeader('Accept', 'application/json, text/html;q=0.5')
        ;

        try {
            $factory->createResponseFromThrowable(new \RuntimeException(), $request);
            self::fail('The selected factory failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** Selected renderer failures preserve identity and do not invoke the fallback renderer. */
    public function testSelectedRendererFailurePropagatesWithoutFallback(): void
    {
        $messages = new Psr17Factory();
        $failure = new \RuntimeException('Synthetic rendering failure');
        $throwable = new \RuntimeException('Original diagnostic');
        $request = $messages->createServerRequest('GET', '/synthetic')->withHeader('Accept', 'application/json');
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::never())->method('createResponseFromThrowable');
        $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
        $renderer->expects(self::once())->method('createResponseFromThrowable')
            ->with($throwable, $request)->willThrowException($failure)
        ;
        $factory = new NegotiatingThrowableResponseFactory($fallback, [
            'application/json' => static fn(): ThrowableResponseFactoryInterface => $renderer,
        ]);

        try {
            $factory->createResponseFromThrowable($throwable, $request);
            self::fail('The selected renderer failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    /** A missing match does not swallow the fallback renderer's own failure. */
    public function testFallbackFailurePropagatesWithTheCurrentRequest(): void
    {
        $messages = new Psr17Factory();
        $failure = new \RuntimeException('Synthetic fallback failure');
        $throwable = new \RuntimeException('Original diagnostic');
        $request = $messages->createServerRequest('GET', '/synthetic');
        $fallback = $this->createMock(ThrowableResponseFactoryInterface::class);
        $fallback->expects(self::once())->method('createResponseFromThrowable')
            ->with($throwable, $request)->willThrowException($failure)
        ;
        $factory = new NegotiatingThrowableResponseFactory($fallback, []);

        try {
            $factory->createResponseFromThrowable($throwable, $request);
            self::fail('The fallback renderer failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }
}
