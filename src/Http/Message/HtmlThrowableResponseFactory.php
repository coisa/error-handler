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

namespace CoiSA\ErrorHandler\Http\Message;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

/**
 * Creates public or explicitly enabled diagnostic HTML through Symfony's rendering APIs.
 *
 * Rendering does not report, emit responses or register process-wide callbacks.
 */
final class HtmlThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ResponseFactoryInterface the application-owned HTTP response factory */
    private ResponseFactoryInterface $responseFactory;

    /** @var StreamFactoryInterface the factory providing a fresh body for every response */
    private StreamFactoryInterface $streamFactory;

    /** @var bool whether trusted application configuration enables diagnostic HTML */
    private bool $debug;

    /**
     * Configures HTML rendering without deriving debug authorization from the request.
     *
     * @param ResponseFactoryInterface $responseFactory the application HTTP response factory
     * @param StreamFactoryInterface   $streamFactory   the application HTTP stream factory
     * @param bool                     $debug           explicit development diagnostics; public HTML is the default
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        bool $debug = false,
    ) {
        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->debug = $debug;
    }

    /**
     * Returns a complete HTML document with the library's HTTP error-status policy.
     *
     * The request never enables diagnostics. Debug HTML may expose exception details;
     * public HTML contains only the status and Symfony's generic error page.
     *
     * @param \Throwable             $throwable the unchanged original diagnostic supplying status and optional details
     * @param ServerRequestInterface $request   the current request; direct selection chooses HTML explicitly
     *
     * @return ResponseInterface the application response containing a fresh UTF-8 HTML stream
     *
     * @throws \Throwable the unchanged HTTP-factory or rendering failure
     */
    public function createResponseFromThrowable(
        \Throwable $throwable,
        ServerRequestInterface $request,
    ): ResponseInterface {
        $code = $throwable->getCode();
        $status = (\is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
        ;
        $body = $this->createHtml($throwable, $status, $response->getReasonPhrase());
        $stream = $this->streamFactory->createStream($body);

        if ($this->debug) {
            $response = $response->withHeader('Cache-Control', 'no-store');
        }

        return $response->withBody($stream);
    }

    /**
     * Adapts Symfony's public body API while retaining the selected PSR-17 status.
     *
     * Symfony may infer a different status for its HTTP exception contracts, so its
     * public setters align the displayed status and text with the returned response.
     * Diagnostic body fragments receive a small document shell and the public stylesheet.
     *
     * @param \Throwable $throwable the original event whose metadata Symfony flattens for rendering
     * @param int        $status    the already selected library HTTP status
     * @param string     $reason    the application response implementation's reason phrase
     *
     * @return string the complete public or diagnostic HTML document
     *
     * @throws \Throwable the unchanged Symfony rendering failure
     */
    private function createHtml(\Throwable $throwable, int $status, string $reason): string
    {
        $renderer = new HtmlErrorRenderer($this->debug, 'UTF-8');
        $exception = FlattenException::createFromThrowable($throwable, $status)
            ->setStatusCode($status)
            ->setStatusText($reason)
        ;
        $body = $renderer->getBody($exception);

        if (!$this->debug) {
            return $body;
        }

        $title = htmlspecialchars($status . ' ' . $reason, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return \sprintf(
            "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n"
            . "<meta charset=\"UTF-8\">\n<meta name=\"robots\" content=\"noindex,nofollow,noarchive\">\n"
            . "<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n"
            . "<title>%s</title>\n<style>%s</style>\n</head>\n"
            . "<body class=\"theme-light\">\n%s\n</body>\n</html>\n",
            $title,
            $renderer->getStylesheet(),
            $body,
        );
    }
}
