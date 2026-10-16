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
use CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Inspector\InspectorInterface;

/**
 * Verifies real Whoops rendering, explicit reporting and caller-owned native state.
 *
 * @internal
 *
 * @covers \CoiSA\ErrorHandler\Http\Message\WhoopsThrowableResponseFactory
 *
 * @uses \CoiSA\ErrorHandler\Http\Message\ReportingThrowableResponseFactory
 */
final class WhoopsThrowableResponseFactoryTest extends TestCase
{
    /** @var array<string, array{bool, mixed}> original ambient values, never rendered by these fixtures */
    private array $globals = [];

    /** Replaces every Whoops superglobal table with synthetic fixture values. */
    protected function setUp(): void
    {
        foreach (['_GET', '_POST', '_FILES', '_COOKIE', '_SESSION', '_SERVER', '_ENV'] as $name) {
            $this->globals[$name] = [\array_key_exists($name, $GLOBALS), $GLOBALS[$name] ?? null];
            $GLOBALS[$name] = [];
        }
    }

    /** Restores the exact caller values and absence of optional superglobals. */
    protected function tearDown(): void
    {
        foreach ($this->globals as $name => [$existed, $value]) {
            if ($existed) {
                $GLOBALS[$name] = $value;
            } else {
                unset($GLOBALS[$name]);
            }
        }
    }

    /**
     * Preserves only integer error statuses and lets PSR-17 select the reason.
     *
     * @param int|string $code   the native throwable code, including SQLSTATE strings
     * @param int        $status the expected HTTP status
     *
     * @dataProvider provideStatusPolicyCases
     */
    public function testStatusPolicy($code, int $status): void
    {
        $original = new class ($code) extends \PDOException {
            /** @param int|string $code the driver-free synthetic code */
            public function __construct($code)
            {
                parent::__construct('A private diagnostic');
                $this->code = $code;
            }
        };
        $messages = new Psr17Factory();
        $calls = 0;
        $renderer = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler a fresh page without application side effects */
            static function () use (&$calls): PrettyPageHandler {
                ++$calls;

                return new PrettyPageHandler();
            },
        );

        self::assertSame(0, $calls);
        $this->expectOutputString('');
        $response = $renderer->createResponseFromThrowable($original);

        self::assertSame(1, $calls);
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($messages->createResponse($status)->getReasonPhrase(), $response->getReasonPhrase());
        self::assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Vary'));
        self::assertStringContainsString('A private diagnostic', (string) $response->getBody());
        self::assertSame($code, $original->getCode());
    }

    /** @return array<string, array{int|string, int}> integer boundaries and non-HTTP codes */
    public static function provideStatusPolicyCases(): iterable
    {
        return [
            'below range' => [399, 500],
            'lower boundary' => [400, 400],
            'client error' => [404, 404],
            'server error' => [503, 503],
            'upper boundary' => [599, 599],
            'above range' => [600, 500],
            'zero' => [0, 500],
            'negative' => [-1, 500],
            'SQLSTATE' => ['HY000', 500],
            'numeric SQLSTATE' => ['23000', 500],
            'numeric-looking HTTP code' => ['404', 500],
        ];
    }

    /** Reports each original once, reuses no page and retains no earlier diagnostic body. */
    public function testReportingAndSequentialPagesPreserveOriginalIdentity(): void
    {
        $messages = new Psr17Factory();
        $pages = [];
        $inspected = [];
        $renderer = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler a page with an application-owned inspector callback */
            static function () use (&$pages, &$inspected): PrettyPageHandler {
                $page = new PrettyPageHandler();
                $page->addDataTableCallback(
                    'Synthetic identity',
                    /** @return array<string, string> controlled diagnostic table values */
                    static function (InspectorInterface $inspector) use (&$inspected): array {
                        $inspected[] = $inspector->getException();

                        return ['fixture' => 'identity recorded'];
                    },
                );
                $pages[] = $page;

                return $page;
            },
        );
        $first = new \RuntimeException(base64_encode('unique first rendered diagnostic'));
        $second = new \RuntimeException(base64_encode('unique second rendered diagnostic'));
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::exactly(2))->method('handleThrowable')
            ->withConsecutive([self::identicalTo($first)], [self::identicalTo($second)])
        ;
        $responses = new ReportingThrowableResponseFactory($reporter, $renderer);

        $firstResponse = $responses->createResponseFromThrowable($first);
        $secondResponse = $responses->createResponseFromThrowable($second);

        self::assertSame([$first, $second], $inspected);
        self::assertCount(2, $pages);
        self::assertNotSame($pages[0], $pages[1]);
        self::assertNotSame($firstResponse->getBody(), $secondResponse->getBody());
        self::assertStringContainsString($first->getMessage(), (string) $firstResponse->getBody());
        self::assertStringContainsString($second->getMessage(), (string) $secondResponse->getBody());
        self::assertStringNotContainsString($first->getMessage(), (string) $secondResponse->getBody());
    }

    /** Proves configured field hiding with positive controls, without claiming total redaction. */
    public function testPageConfigurationHidesSelectedSyntheticGlobalsAndEscapesMessages(): void
    {
        $messages = new Psr17Factory();
        $_SERVER['HTTP_AUTHORIZATION'] = base64_encode('synthetic authorization secret');
        $_COOKIE['session'] = base64_encode('synthetic cookie secret');
        $_ENV['APP_SECRET'] = base64_encode('synthetic environment secret');
        $original = new \RuntimeException('<synthetic-tag>');

        $default = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler the upstream defaults used as a positive exposure control */
            static fn(): PrettyPageHandler => new PrettyPageHandler(),
        );
        $visible = (string) $default->createResponseFromThrowable($original)->getBody();

        $configured = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler the page with explicit application field-hiding policy */
            static function (): PrettyPageHandler {
                $page = new PrettyPageHandler();
                $page->hideSuperglobalKey('_SERVER', 'HTTP_AUTHORIZATION');
                $page->hideSuperglobalKey('_COOKIE', 'session');
                $page->hideSuperglobalKey('_ENV', 'APP_SECRET');

                return $page;
            },
        );
        $hidden = (string) $configured->createResponseFromThrowable($original)->getBody();

        foreach ([$_SERVER['HTTP_AUTHORIZATION'], $_COOKIE['session'], $_ENV['APP_SECRET']] as $secret) {
            self::assertStringContainsString($secret, $visible);
            self::assertStringNotContainsString($secret, $hidden);
        }
        self::assertStringContainsString('&lt;synthetic-tag&gt;', $hidden);
        self::assertStringNotContainsString('<synthetic-tag>', $hidden);
        self::assertSame('<synthetic-tag>', $original->getMessage());
    }

    /** A failing reporter prevents page creation and is never retried. */
    public function testReporterFailurePreventsRendering(): void
    {
        $messages = new Psr17Factory();
        $calls = 0;
        $failure = new \LogicException('Synthetic private reporter failure');
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->willThrowException($failure);
        $renderer = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler the page that must not be created */
            static function () use (&$calls): PrettyPageHandler {
                ++$calls;

                return new PrettyPageHandler();
            },
        );

        try {
            (new ReportingThrowableResponseFactory($reporter, $renderer))
                ->createResponseFromThrowable(new \RuntimeException('Original event'))
            ;
            self::fail('The private reporter failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame(0, $calls);
    }

    /**
     * Preserves caller buffers/callbacks after real rendering and a real Whoops handler failure.
     *
     * @param bool $fails whether the application page fails during Whoops rendering
     *
     * @dataProvider provideCallerStateSurvivesRenderingCases
     *
     * @runInSeparateProcess
     *
     * @preserveGlobalState disabled
     */
    public function testCallerStateSurvivesRendering(bool $fails): void
    {
        $messages = new Psr17Factory();
        $failure = new \LogicException('Synthetic page failure');
        $page = $fails ? $this->failingPage($failure) : new PrettyPageHandler();
        $renderer = new WhoopsThrowableResponseFactory(
            $messages,
            $messages,
            /** @return PrettyPageHandler the single fresh fixture page */
            static fn(): PrettyPageHandler => $page,
        );

        /** @return bool the previous callback owns the synthetic warning */
        $errorCallback = static fn(): bool => true;

        /**
         * @param \Throwable $event an unexpected native exception
         *
         * @throws \LogicException if native delivery unexpectedly reaches the sentinel
         */
        $exceptionCallback = static function (\Throwable $event): void {
            throw new \LogicException('Unexpected native delivery.', 0, $event);
        };
        set_error_handler($errorCallback);
        set_exception_handler($exceptionCallback);
        $entry = ob_get_level();
        ob_start();
        echo 'caller-prefix';
        $caught = null;

        try {
            try {
                $renderer->createResponseFromThrowable(new \RuntimeException('Original event'));
            } catch (\Throwable $error) {
                $caught = $error;
            }
            self::assertSame($fails ? $failure : null, $caught);
            self::assertSame($entry + 1, ob_get_level());
            self::assertSame('caller-prefix', ob_get_contents());
            self::assertSame($errorCallback, set_error_handler($errorCallback));
            restore_error_handler();
            self::assertSame($exceptionCallback, set_exception_handler($exceptionCallback));
            restore_exception_handler();
        } finally {
            restore_error_handler();
            restore_exception_handler();
            while (ob_get_level() > $entry) {
                ob_end_clean();
            }
        }
    }

    /** @return array<string, array{bool}> successful and failed rendering */
    public static function provideCallerStateSurvivesRenderingCases(): iterable
    {
        return ['success' => [false], 'page failure' => [true]];
    }

    /**
     * Propagates collaborator failures without retry or duplicate reporting.
     *
     * @param string $stage the collaborator that raises the distinct failure
     *
     * @dataProvider provideFailuresPropagateUnchangedCases
     */
    public function testFailuresPropagateUnchanged(string $stage): void
    {
        $failure = new \LogicException('Synthetic ' . $stage . ' failure');
        $original = new \RuntimeException('Original event');
        $messages = new Psr17Factory();
        $responses = $this->createMock(ResponseFactoryInterface::class);
        $expectation = $responses->expects(self::once())->method('createResponse')->with(500);
        if ('response' === $stage) {
            $expectation->willThrowException($failure);
        } else {
            $expectation->willReturn($messages->createResponse(500));
        }
        $streams = $this->createMock(StreamFactoryInterface::class);
        if ('stream' === $stage) {
            $streams->expects(self::once())->method('createStream')->willThrowException($failure);
        } else {
            $streams->expects(self::never())->method('createStream');
        }
        $calls = 0;
        $renderer = new WhoopsThrowableResponseFactory(
            $responses,
            $streams,
            /**
             * @return PrettyPageHandler the application page, unless its factory fails
             *
             * @throws \LogicException the unchanged page-factory failure
             */
            function () use ($stage, $failure, &$calls): PrettyPageHandler {
                ++$calls;
                if ('page factory' === $stage) {
                    throw $failure;
                }

                return 'Whoops' === $stage ? $this->failingPage($failure) : new PrettyPageHandler();
            },
        );
        $reporter = $this->createMock(ThrowableHandlerInterface::class);
        $reporter->expects(self::once())->method('handleThrowable')->with(self::identicalTo($original));

        try {
            (new ReportingThrowableResponseFactory($reporter, $renderer))->createResponseFromThrowable($original);
            self::fail('The original collaborator failure must propagate.');
        } catch (\LogicException $caught) {
            self::assertSame($failure, $caught);
        }
        self::assertSame('response' === $stage ? 0 : 1, $calls);
    }

    /** @return array<string, array{string}> independently failing collaborators */
    public static function provideFailuresPropagateUnchangedCases(): iterable
    {
        return [
            'PSR response' => ['response'],
            'page factory' => ['page factory'],
            'Whoops handler' => ['Whoops'],
            'PSR stream' => ['stream'],
        ];
    }

    /**
     * Enforces the documented page type at the native PHP return boundary.
     *
     * @param mixed $value the deliberately invalid page-factory result
     *
     * @dataProvider provideRejectsInvalidPageCases
     */
    public function testRejectsInvalidPage($value): void
    {
        $messages = new Psr17Factory();

        /** @return mixed a deliberately malformed application result */
        $factory = static fn() => $value;

        // Deliberately violate PHPDoc to exercise the native return-type boundary.
        /** @var callable(): PrettyPageHandler $factory */
        $renderer = new WhoopsThrowableResponseFactory($messages, $messages, $factory);

        $this->expectException(\TypeError::class);
        $renderer->createResponseFromThrowable(new \RuntimeException('Original event'));
    }

    /** @return array<string, array{mixed}> invalid null, scalar and unrelated object results */
    public static function provideRejectsInvalidPageCases(): iterable
    {
        return ['null' => [null], 'scalar' => ['page'], 'object' => [new \stdClass()]];
    }

    /**
     * Builds an application page that fails through the real Whoops public pipeline.
     *
     * @param \LogicException $failure the exact configured failure
     *
     * @return PrettyPageHandler the fresh failing page
     */
    private function failingPage(\LogicException $failure): PrettyPageHandler
    {
        return new class ($failure) extends PrettyPageHandler {
            /** @var \LogicException the unchanged synthetic rendering failure */
            private \LogicException $failure;

            /** @param \LogicException $failure the exact handler failure */
            public function __construct(\LogicException $failure)
            {
                parent::__construct();
                $this->failure = $failure;
            }

            /**
             * @return int no normal handler result is returned
             *
             * @throws \LogicException the configured failure raised inside Whoops
             */
            public function handle(): int
            {
                throw $this->failure;
            }
        };
    }
}
