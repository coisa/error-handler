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

namespace CoiSA\ErrorHandler\ServiceProvider\Factory;

use CoiSA\ErrorHandler\Http\Message\HtmlThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\JsonThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\NegotiatingThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactory;
use CoiSA\ErrorHandler\Http\Message\ThrowableResponseFactoryInterface;
use Psr\Container\ContainerInterface;

/** Composes lazy representations without resolving a current request or development page. */
final class NegotiatingThrowableResponseFactoryFactory
{
    /**
     * Creates a reusable selector; only the selected representation is constructed on a failure.
     *
     * The HTML candidate includes the renderer's charset so parameter-specific
     * preferences and exclusions apply to the representation actually returned.
     *
     * @param ContainerInterface $container the resolver supplying concrete pure renderers
     *
     * @return NegotiatingThrowableResponseFactory the request-independent negotiation service
     *
     * @throws \Throwable when construction of the required generic fallback fails
     */
    public function __invoke(ContainerInterface $container): NegotiatingThrowableResponseFactory
    {
        $fallback = self::resolveRenderer($container, ThrowableResponseFactory::class);

        return new NegotiatingThrowableResponseFactory($fallback, [
            'text/plain' => static fn(): ThrowableResponseFactoryInterface => $fallback,
            'application/json' => static fn(): ThrowableResponseFactoryInterface => self::resolveRenderer(
                $container,
                JsonThrowableResponseFactory::class,
            ),
            'application/problem+json' => static fn(): ThrowableResponseFactoryInterface => self::resolveRenderer(
                $container,
                JsonThrowableResponseFactory::class,
            ),
            'text/html; charset=UTF-8' => static fn(): ThrowableResponseFactoryInterface => self::resolveRenderer(
                $container,
                HtmlThrowableResponseFactory::class,
            ),
        ]);
    }

    /**
     * Validates an explicitly configured renderer without retrying a different representation.
     *
     * @param ContainerInterface                              $container the application resolver
     * @param class-string<ThrowableResponseFactoryInterface> $id        the selected renderer service
     *
     * @return ThrowableResponseFactoryInterface the configured renderer
     *
     * @throws \Throwable the unchanged service construction failure
     * @throws \TypeError when a definition returns an incompatible renderer
     */
    private static function resolveRenderer(
        ContainerInterface $container,
        string $id,
    ): ThrowableResponseFactoryInterface {
        $renderer = $container->get($id);
        if (!$renderer instanceof ThrowableResponseFactoryInterface) {
            throw new \TypeError($id . ' must implement ' . ThrowableResponseFactoryInterface::class . '.');
        }

        return $renderer;
    }
}
