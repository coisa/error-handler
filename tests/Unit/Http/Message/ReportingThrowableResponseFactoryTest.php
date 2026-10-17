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

use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

/**
 * Verifies one explicit diagnostic delivery before pure response rendering.
 *
 * @internal
 */
#[CoversClass(ReportingThrowableResponseFactory::class)]
final class ReportingThrowableResponseFactoryTest extends TestCase
{
    /** Reporting precedes rendering with exact throwable and response identities. */
    public function testReportsOnceBeforeRenderingTheSameThrowable(): void
    {
        $this->expectOutputString('');
        $throwable = new \Error('Original diagnostic');
        $response = (new Psr17Factory())->createResponse(500);
        $calls = [];
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')
            ->with(self::identicalTo($throwable))
            ->willReturnCallback(
                /** Records only the order of the private reporting collaboration. */
                static function () use (&$calls): void {
                    $calls[] = 'report';
                },
            )
        ;
        $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
        $renderer->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($throwable))
            ->willReturnCallback(
                /**
                 * Returns the application renderer's exact response after recording order.
                 *
                 * @return ResponseInterface the response that the decorator must preserve
                 */
                static function () use (&$calls, $response): ResponseInterface {
                    $calls[] = 'render';

                    return $response;
                },
            )
        ;
        $bufferLevel = ob_get_level();

        $actual = (new ReportingThrowableResponseFactory($reporter, $renderer))
            ->createResponseFromThrowable($throwable)
        ;

        self::assertSame($response, $actual);
        self::assertSame(['report', 'render'], $calls);
        self::assertSame($bufferLevel, ob_get_level());
    }

    /** A reporter failure blocks rendering and propagates as the same object. */
    public function testReporterFailurePreventsRendering(): void
    {
        $this->expectOutputString('');
        $throwable = new \RuntimeException('Original diagnostic');
        $failure = new \LogicException('Private reporter failed');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')
            ->with(self::identicalTo($throwable))
            ->willThrowException($failure)
        ;
        $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
        $renderer->expects(self::never())->method('createResponseFromThrowable');
        $caught = null;

        try {
            (new ReportingThrowableResponseFactory($reporter, $renderer))->createResponseFromThrowable($throwable);
        } catch (\Throwable $actual) {
            $caught = $actual;
        }

        self::assertSame($failure, $caught);
    }

    /** A renderer failure does not cause a second diagnostic delivery. */
    public function testPropagatesRendererFailureWithoutReportingAgain(): void
    {
        $this->expectOutputString('');
        $throwable = new \RuntimeException('Original diagnostic');
        $failure = new \LogicException('Response renderer failed');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($throwable));
        $renderer = $this->createMock(ThrowableResponseFactoryInterface::class);
        $renderer->expects(self::once())->method('createResponseFromThrowable')
            ->with(self::identicalTo($throwable))
            ->willThrowException($failure)
        ;
        $caught = null;

        try {
            (new ReportingThrowableResponseFactory($reporter, $renderer))->createResponseFromThrowable($throwable);
        } catch (\Throwable $actual) {
            $caught = $actual;
        }

        self::assertSame($failure, $caught);
    }

    /** Reporter output is neither silenced nor copied into the pure JSON response. */
    public function testReporterRetainsOwnershipOfItsOutput(): void
    {
        $this->expectOutputString('Reporter-owned output');
        $messages = new Psr17Factory();
        $reporter = new CallableThrowableHandler(
            /** Demonstrates an application reporter whose output remains its own responsibility. */
            static function (): void {
                echo 'Reporter-owned output';
            },
        );

        $response = (new ReportingThrowableResponseFactory(
            $reporter,
            new JsonThrowableResponseFactory($messages, $messages),
        ))->createResponseFromThrowable(new \RuntimeException('Original diagnostic'));

        self::assertSame('{"error":"Request failed"}', (string) $response->getBody());
    }
}
