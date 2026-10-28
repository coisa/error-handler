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
use CoiSA\ErrorHandler\Handler\SymfonyCliThrowableHandler;
use CoiSA\ErrorHandler\ServiceProvider\ErrorHandlerServiceProvider;
use FastForward\Container\Factory\ServiceFactory;
use FastForward\Container\ServiceProvider\ArrayServiceProvider;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

use function FastForward\Container\container;

require __DIR__ . '/bootstrap.php';

$mode = $argv[1] ?? 'direct';
exampleCheck(in_array($mode, ['direct', 'provider', 'console'], true), 'Choose direct, provider or console.');

// BufferedOutput makes this synthetic recipe observable without printing private diagnostics.
// Omit the constructor argument in an application to write directly to stderr.
$output = new BufferedOutput();
$terminal = new SymfonyCliThrowableHandler($output);
$errors = new ErrorHandler($terminal);
$failure = new RuntimeException('Synthetic terminal diagnostic');

if ('provider' === $mode) {
    $application = new ArrayServiceProvider([
        OutputInterface::class => new ServiceFactory($output),
    ]);
    $services = container($application, new ErrorHandlerServiceProvider(cli: true));
    $errors = $services->get(ErrorHandler::class);
}

if ('console' === $mode) {
    $application = new Application('Synthetic command');
    $application->setAutoExit(false);
    $application->setCatchExceptions(false);
    $application->addCommands([new class ($failure) extends Command {
        /** @param Throwable $failure the unchanged synthetic command failure */
        public function __construct(private readonly Throwable $failure)
        {
            parent::__construct('fail');
        }

        /**
         * Fail without letting Console become a second exception renderer.
         *
         * @param InputInterface  $input  the synthetic command input
         * @param OutputInterface $output the application-owned command output
         *
         * @return int this synthetic command never returns normally
         *
         * @throws Throwable the unchanged command failure
         */
        protected function execute(InputInterface $input, OutputInterface $output): int
        {
            throw $this->failure;
        }
    }]);

    // The bootstrap owns this catch and reports once; Console's exception catch is disabled.
    try {
        $application->run(new ArrayInput(['command' => 'fail']), $output);
    } catch (Throwable $caught) {
        exampleCheck($failure === $caught, 'The original command failure must reach the bootstrap.');
        $errors->handleThrowable($caught);
    }
} else {
    $errors->handleThrowable($failure);
}

$diagnostic = $output->fetch();
exampleCheck(1 === substr_count($diagnostic, $failure->getMessage()), 'Expected one terminal diagnostic.');
exampleCheck(str_contains($diagnostic, RuntimeException::class), 'Expected Symfony exception formatting.');

// Reporting does not set an exit status; this verification program succeeds intentionally.
echo 'Symfony CLI ' . $mode . ': one terminal diagnostic and explicit capture ownership passed' . PHP_EOL;
