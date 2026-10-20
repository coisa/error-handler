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
use Psr\Http\Message\StreamFactoryInterface;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/**
 * Renders explicitly selected development HTML without reporting or HTTP emission.
 *
 * Applications own debug authorization and page configuration. Diagnostic
 * HTML can expose private values; hiding selected superglobal keys is not full redaction.
 */
final class WhoopsThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ResponseFactoryInterface the application-owned HTTP response factory */
    private ResponseFactoryInterface $responseFactory;

    /** @var StreamFactoryInterface the application-owned HTTP stream factory */
    private StreamFactoryInterface $streamFactory;

    /** @var callable(): PrettyPageHandler supplies the configured page for each render */
    private $pageHandlerFactory;

    /**
     * Configures optional development rendering without constructing a page or Run.
     *
     * @param ResponseFactoryInterface      $responseFactory    the application HTTP response factory
     * @param StreamFactoryInterface        $streamFactory      the application HTTP stream factory
     * @param callable(): PrettyPageHandler $pageHandlerFactory supplies a page without emission or registration
     *
     * @throws \LogicException when the optional Whoops dependency is unavailable
     */
    public function __construct(
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        callable $pageHandlerFactory,
    ) {
        if (!class_exists(Run::class) || !class_exists(PrettyPageHandler::class)) {
            throw new \LogicException('Install filp/whoops:^2.18.7 to use WhoopsThrowableResponseFactory.');
        }

        $this->responseFactory = $responseFactory;
        $this->streamFactory = $streamFactory;
        $this->pageHandlerFactory = $pageHandlerFactory;
    }

    /**
     * Returns a new HTML response with the original failure's development diagnostics.
     *
     * Integer codes from 400 through 599 are preserved; other codes use 500.
     * This renderer does not report, emit, register callbacks or negotiate Accept.
     *
     * @param \Throwable $throwable the unchanged original failure to render
     *
     * @return ResponseInterface the development response with a fresh HTML body and no-store policy
     *
     * @throws \Throwable the unchanged response, stream, page-factory or rendering failure
     * @throws \TypeError when the page factory returns an incompatible value
     */
    public function createResponseFromThrowable(\Throwable $throwable): ResponseInterface
    {
        $code = $throwable->getCode();
        $status = (\is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Cache-Control', 'no-store')
        ;
        $stream = $this->streamFactory->createStream($this->createHtml($throwable));

        return $response->withBody($stream);
    }

    /**
     * Uses Whoops' public string-returning path with a fresh Run and no competing callbacks.
     *
     * @param \Throwable $throwable the original failure supplied to Whoops
     *
     * @return string the generated HTML, without capturing application reporter output
     *
     * @throws \Throwable the unchanged page-factory or Whoops failure
     */
    private function createHtml(\Throwable $throwable): string
    {
        $page = $this->createPageHandler();
        $page->handleUnconditionally(true);

        $run = new Run();
        $run->allowQuit(false);
        $run->writeToOutput(false);
        $run->sendHttpCode(false);
        $run->pushHandler($page);

        return $run->handleException($throwable);
    }

    /**
     * Obtains the application-configured page for the current sequential render.
     *
     * Whoops replaces its exception and inspector state on each render. A shared page
     * retains its configuration and latest diagnostic until the following render.
     *
     * @return PrettyPageHandler the page supplied by the application
     *
     * @throws \Throwable the unchanged factory failure
     * @throws \TypeError when the application returns an incompatible value
     */
    private function createPageHandler(): PrettyPageHandler
    {
        return ($this->pageHandlerFactory)();
    }
}
