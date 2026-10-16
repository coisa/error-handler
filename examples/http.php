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

use CoiSA\ErrorHandler\ErrorHandler;
use CoiSA\ErrorHandler\Handler\CallableThrowableHandler;
use CoiSA\ErrorHandler\Handler\ThrowableHandlerInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use CoiSA\ErrorHandler\Http\Message\ThrowableStreamFactory;
use CoiSA\ErrorHandler\Http\Middleware\ErrorHandlerMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'capture';
$reports = 0;
$messages = new Psr17Factory();

$reporter = new CallableThrowableHandler(
    /**
     * Render synthetic public output or count private reporting in the safe route.
     *
     * @param Throwable $error the synthetic downstream failure
     *
     * @throws RuntimeException in the reporter-failure fixture only
     */
    static function (Throwable $error) use ($mode, &$reports): void {
        ++$reports;

        if ('capture' === $mode) {
            echo 'Public example response';
        } elseif ('zero' === $mode) {
            echo '0';
        } elseif ('reporter-failure' === $mode) {
            throw new RuntimeException('Reporter fixture failed');
        }
    },
);
$errors = new ErrorHandler($reporter);

$responses = new ThrowableResponseFactory(new ThrowableStreamFactory($messages, $reporter), $messages);

if ('safe' === $mode) {
    // This is an application adapter, not a built-in pure renderer.
    $responses = new class ($messages, $reporter) implements ThrowableResponseFactoryInterface {
        /** @var Psr17Factory the application-owned response and stream implementation */
        private Psr17Factory $messages;

        /** @var ThrowableHandlerInterface the private diagnostic reporter */
        private ThrowableHandlerInterface $reporter;

        /**
         * Keep diagnostic delivery and public response composition explicit.
         *
         * @param Psr17Factory              $messages the application HTTP factories
         * @param ThrowableHandlerInterface $reporter the private diagnostic destination
         */
        public function __construct(Psr17Factory $messages, ThrowableHandlerInterface $reporter)
        {
            $this->messages = $messages;
            $this->reporter = $reporter;
        }

        /**
         * Report once, then build a fixed public response without exception details.
         *
         * @param Throwable $error the downstream failure to report privately
         *
         * @return ResponseInterface the fixed public error response
         */
        public function createResponseFromThrowable(Throwable $error): ResponseInterface
        {
            $this->reporter->handleThrowable($error);

            return $this->messages->createResponse(500)->withBody(
                $this->messages->createStream('Internal Server Error'),
            );
        }
    };
} elseif ('renderer-failure' === $mode) {
    $responses = new class implements ThrowableResponseFactoryInterface {
        /**
         * Demonstrate cleanup when an application renderer fails.
         *
         * @param Throwable $error the downstream failure
         *
         * @return ResponseInterface no response is returned by this failing fixture
         *
         * @throws RuntimeException the synthetic renderer failure
         */
        public function createResponseFromThrowable(Throwable $error): ResponseInterface
        {
            throw new RuntimeException('Renderer fixture failed');
        }
    };
}

$next = new class ($messages, $mode) implements RequestHandlerInterface {
    /** @var Psr17Factory the application-owned HTTP factories */
    private Psr17Factory $messages;

    /** @var string the selected synthetic downstream route */
    private string $mode;

    /**
     * Store the synthetic request route.
     *
     * @param Psr17Factory $messages the application HTTP factories
     * @param string       $mode     the selected example route
     */
    public function __construct(Psr17Factory $messages, string $mode)
    {
        $this->messages = $messages;
        $this->mode = $mode;
    }

    /**
     * Return a normal PSR response or throw a synthetic downstream failure.
     *
     * @param ServerRequestInterface $request the synthetic request
     *
     * @return ResponseInterface the successful route's response
     *
     * @throws RuntimeException for an error-response fixture
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ('success' === $this->mode) {
            return $this->messages->createResponse(200)->withBody($this->messages->createStream('OK'));
        }

        throw new RuntimeException('Public example failure', 404);
    }
};

/** Represent a surrounding application's PHP error callback. */
$previousError = static fn(): bool => true;

/**
 * Represent a surrounding application's exception callback.
 *
 * @param Throwable $error an unexpected failure escaping middleware
 *
 * @throws LogicException if the surrounding callback is unexpectedly invoked
 */
$previousException = static function (Throwable $error): void {
    throw new LogicException('The surrounding exception callback must remain unused.', 0, $error);
};

set_error_handler($previousError);
set_exception_handler($previousException);

$middleware = new ErrorHandlerMiddleware($errors, $responses);
$bufferLevel = ob_get_level();
$response = null;
$bufferDelta = 0;

try {
    $response = $middleware->process($messages->createServerRequest('GET', '/example'), $next);
} catch (RuntimeException $error) {
    exampleCheck(in_array($mode, ['renderer-failure', 'reporter-failure'], true), 'Unexpected HTTP fixture failure.');
    exampleCheck(false !== strpos($error->getMessage(), 'fixture failed'), 'Failure identity changed.');

    // Observe the existing output-buffer leak, then clean only this fixture's new buffers.
    $bufferDelta = ob_get_level() - $bufferLevel;
} finally {
    while (ob_get_level() > $bufferLevel) {
        ob_end_clean();
    }

    $actualError = set_error_handler($previousError);
    restore_error_handler();
    $actualException = set_exception_handler($previousException);
    restore_exception_handler();

    restore_error_handler();
    restore_exception_handler();

    exampleCheck(
        $actualError === $previousError && $actualException === $previousException,
        'Middleware lost surrounding callbacks.',
    );
}

if ('renderer-failure' === $mode || 'reporter-failure' === $mode) {
    exampleCheck(null === $response, 'A failed renderer unexpectedly returned a response.');
    exampleCheck(('reporter-failure' === $mode ? 1 : 0) === $bufferDelta, 'The documented buffer behavior changed.');
} else {
    exampleCheck($response instanceof ResponseInterface, 'Middleware did not return a PSR response.');

    $body = (string) $response->getBody();

    if ('capture' === $mode) {
        exampleCheck(
            'Public example response' === $body && 404 === $response->getStatusCode(),
            'Captured response changed.',
        );
        exampleCheck(
            'Public example failure' === $response->getReasonPhrase(),
            'The existing reason phrase policy changed.',
        );
    } elseif ('safe' === $mode) {
        exampleCheck(
            'Internal Server Error' === $body && 500 === $response->getStatusCode(),
            'Safe application response changed.',
        );
    } elseif ('success' === $mode) {
        exampleCheck('OK' === $body && 200 === $response->getStatusCode(), 'Normal response changed.');
    } else {
        exampleCheck(in_array($mode, ['empty', 'zero'], true), 'Unknown HTTP example mode.');
        exampleCheck(
            false !== strpos($body, 'Public example failure') && false !== strpos($body, 'RuntimeException'),
            'The existing fallback changed.',
        );
    }
}

exampleCheck(
    ('success' === $mode || 'renderer-failure' === $mode ? 0 : 1) === $reports,
    'Unexpected reporting count.',
);

echo 'HTTP ' . $mode . ': response/reporting contract and callback restoration passed; buffer delta '
    . $bufferDelta . PHP_EOL;
