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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\AcceptHeader;
use Symfony\Component\HttpFoundation\AcceptHeaderItem;

/**
 * Selects an application-authorized renderer for the current request's Accept header.
 *
 * The application supplies the available representations in server-preference order.
 * Only the winning renderer callback runs. Diagnostic authorization belongs to
 * that configuration; a client header cannot enable an unavailable representation.
 * This factory does not report, register callbacks, retain requests or emit output.
 */
final class NegotiatingThrowableResponseFactory implements ThrowableResponseFactoryInterface
{
    /** @var ThrowableResponseFactoryInterface the generic response for absent or unsupported preferences */
    private readonly ThrowableResponseFactoryInterface $fallback;

    /** @var array<string, callable(): ThrowableResponseFactoryInterface> lazy representations in tie-breaking order */
    private readonly array $renderers;

    /**
     * Configures trusted candidates without resolving any lazy renderer.
     *
     * @param ThrowableResponseFactoryInterface                            $fallback  generic fallback
     * @param array<string, callable(): ThrowableResponseFactoryInterface> $renderers lazy types in priority order
     */
    public function __construct(ThrowableResponseFactoryInterface $fallback, array $renderers)
    {
        $this->fallback = $fallback;
        $this->renderers = $renderers;
    }

    /**
     * Negotiates positive client quality using Symfony's media-range matching.
     *
     * Specific ranges determine each candidate's effective quality, including zero
     * exclusions behind wildcards. Equal positive qualities retain configured order.
     * Missing or unsupported preferences select the generic fallback. Selected map
     * keys define the response media type; matching renderer parameters are retained
     * when the key has none. Every response includes Accept in the Vary policy.
     *
     * @param \Throwable             $throwable the original diagnostic, passed unchanged to the selected renderer
     * @param ServerRequestInterface $request   the current request, never retained between invocations
     *
     * @return ResponseInterface the selected response without reporting or emission
     *
     * @throws \Throwable the identical lazy factory or renderer failure; no fallback retry is performed
     */
    public function createResponseFromThrowable(
        \Throwable $throwable,
        ServerRequestInterface $request,
    ): ResponseInterface {
        [$renderer, $mediaType] = $this->selectRenderer($request);
        $response = $renderer->createResponseFromThrowable($throwable, $request);

        if (null !== $mediaType) {
            $selected = AcceptHeaderItem::fromString($mediaType);
            if (
                !$response->hasHeader('Content-Type')
                || [] !== $selected->getAttributes()
                || 0 !== strcasecmp(
                    $selected->getValue(),
                    AcceptHeaderItem::fromString($response->getHeaderLine('Content-Type'))->getValue(),
                )
            ) {
                $response = $response->withHeader('Content-Type', $mediaType);
            }
        } elseif (!$response->hasHeader('Content-Type')) {
            $response = $response->withHeader('Content-Type', 'text/plain');
        }

        return $this->varyByAccept($response);
    }

    /**
     * Obtains only the best positive candidate or the already configured fallback.
     *
     * @param ServerRequestInterface $request the request supplying representation preferences
     *
     * @return array{ThrowableResponseFactoryInterface, ?string} the renderer and selected map key, or null for fallback
     *
     * @throws \Throwable the identical selected callback failure
     */
    private function selectRenderer(ServerRequestInterface $request): array
    {
        $accept = AcceptHeader::fromString($request->getHeaderLine('Accept'));
        $quality = 0.0;
        $selected = null;
        $selectedMediaType = null;

        foreach ($this->renderers as $mediaType => $renderer) {
            $item = $accept->get($mediaType);
            if (null === $item || $item->getQuality() <= $quality) {
                continue;
            }

            $quality = $item->getQuality();
            $selected = $renderer;
            $selectedMediaType = $mediaType;
        }

        return null === $selected ? [$this->fallback, null] : [$selected(), $selectedMediaType];
    }

    /**
     * Adds the selecting field without replacing existing Vary fields or duplicating Accept.
     *
     * @param ResponseInterface $response the selected renderer's immutable response
     *
     * @return ResponseInterface the response with a merged Vary policy
     */
    private function varyByAccept(ResponseInterface $response): ResponseInterface
    {
        foreach ($response->getHeader('Vary') as $line) {
            foreach (explode(',', $line) as $field) {
                $field = strtolower(trim($field));
                if ('accept' === $field || '*' === $field) {
                    return $response;
                }
            }
        }

        return $response->withAddedHeader('Vary', 'Accept');
    }
}
