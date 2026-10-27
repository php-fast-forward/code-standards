<?php

declare(strict_types=1);

namespace FastForward\CodeStandards\Console;

use FastForward\CodeStandards\Console\Command\CheckCommand;
use FastForward\CodeStandards\Console\Command\FixCommand;
use FastForward\CodeStandards\Console\Command\MagicNumbersCommand;
use FastForward\CodeStandards\Console\Command\RefactorCommand;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/** Native Symfony application; standard binding errors retain the CLI's invalid-input status. */
final class Application extends SymfonyApplication
{
    /** Registers independent operations without resolving project state or executing tools. */
    public function __construct(
        CheckCommand $check,
        FixCommand $fix,
        RefactorCommand $refactor,
        MagicNumbersCommand $magicNumbers,
    ) {
        parent::__construct('Fast Forward Code Standards');

        $this->addCommand($check);
        $this->addCommand($fix);
        $this->addCommand($refactor);
        $this->addCommand($magicNumbers);
        $this->setAutoExit(false);
    }

    /**
     * Delegates parsing/help to Symfony and renders controlled binding failures on the error stream.
     *
     * @return int Command status, or INVALID when Symfony rejects a command, argument or option.
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        try {
            return parent::doRun($input, $output);
        } catch (ExceptionInterface $exception) {
            $errorOutput = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $this->renderThrowable($exception, $errorOutput);

            return Command::INVALID;
        }
    }
}
