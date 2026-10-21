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

namespace CoiSA\ErrorHandler\Handler;

use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;

/**
 * Formats explicitly selected terminal diagnostics without installing native callbacks.
 *
 * The optional Console output belongs to the application; otherwise diagnostics go
 * to stderr. This adapter does not choose a SAPI, terminate the process or render HTTP.
 */
final class SymfonyCliThrowableHandler implements ThrowableHandlerInterface
{
    /**
     * Selects an application-owned Console destination without requiring Console for stderr output.
     *
     * @param null|OutputInterface $output optional Console output; ConsoleOutput uses its error channel
     */
    public function __construct(private readonly ?OutputInterface $output = null)
    {
    }

    /**
     * Writes the original throwable's Symfony diagnostic once to the selected terminal destination.
     *
     * Console markup is not interpreted. No registration, reporting retry, exit status
     * policy or HTTP negotiation occurs here. Diagnostic text may contain private data.
     *
     * @param \Throwable $throwable the original diagnostic, passed unchanged to Symfony
     *
     * @throws \Throwable        the unchanged renderer or application-output failure
     * @throws \RuntimeException when the native stderr stream cannot accept the complete diagnostic
     */
    public function handleThrowable(\Throwable $throwable): void
    {
        $text = (new CliErrorRenderer())->render($throwable)->getAsString();
        if (null === $this->output) {
            $this->writeToStderr($text);

            return;
        }

        $output = $this->output instanceof ConsoleOutputInterface ? $this->output->getErrorOutput() : $this->output;
        $output->write($text, false, OutputInterface::OUTPUT_RAW);
    }

    /**
     * Writes all bytes to an owned stderr handle without closing an application's stream constant.
     *
     * @param string $text the already formatted diagnostic
     *
     * @throws \RuntimeException when opening or writing the native destination fails
     * @throws \Throwable        the unchanged native warning-handler failure
     */
    private function writeToStderr(string $text): void
    {
        $stream = fopen('php://stderr', 'w');
        if (false === $stream) {
            throw new \RuntimeException('Unable to open stderr for the CLI diagnostic.');
        }

        try {
            $offset = 0;
            $length = \strlen($text);
            while ($offset < $length) {
                $written = fwrite($stream, substr($text, $offset));
                if (false === $written || 0 === $written) {
                    throw new \RuntimeException('Unable to write the complete CLI diagnostic to stderr.');
                }
                $offset += $written;
            }
        } finally {
            fclose($stream);
        }
    }
}
