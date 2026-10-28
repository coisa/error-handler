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

use CoiSA\ErrorHandler\Http\Message\GenericThrowableStreamFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Verifies the provider's generic renderer delegates only the fixed public body.
 *
 * @internal
 */
#[CoversClass(GenericThrowableStreamFactory::class)]
final class GenericThrowableStreamFactoryTest extends TestCase
{
    /**
     * Preserves the exact returned stream without consulting diagnostic text or emitting output.
     *
     * @param class-string<\Throwable> $class the real throwable category
     */
    #[DataProvider('provideGenericBodyIgnoresDiagnosticDetailsCases')]
    public function testGenericBodyIgnoresDiagnosticDetails(string $class): void
    {
        $this->expectOutputString('');
        $original = new $class("private detail\r\nX-Injected: no", 503);
        $stream = (new Psr17Factory())->createStream('Internal Server Error');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')
            ->with('Internal Server Error')->willReturn($stream)
        ;

        $result = (new GenericThrowableStreamFactory($streams))->createStreamFromThrowable($original);

        self::assertSame($stream, $result);
        self::assertSame('Internal Server Error', (string) $result);
        self::assertSame("private detail\r\nX-Injected: no", $original->getMessage());
    }

    /** @return array<string, array{class-string<\Throwable>}> exception and engine-error diagnostics */
    public static function provideGenericBodyIgnoresDiagnosticDetailsCases(): iterable
    {
        return ['exception' => [\RuntimeException::class], 'engine error' => [\Error::class]];
    }

    /** Preserves the original stream-backend failure after one attempt without output or retry. */
    public function testStreamFailurePropagatesWithoutRetry(): void
    {
        $this->expectOutputString('');
        $primary = new \LogicException('stream backend failure');
        $streams = $this->createMock(StreamFactoryInterface::class);
        $streams->expects(self::once())->method('createStream')
            ->with('Internal Server Error')->willThrowException($primary)
        ;

        try {
            (new GenericThrowableStreamFactory($streams))->createStreamFromThrowable(new \Error('private'));
            self::fail('The backend failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($primary, $caught);
        }
    }
}
