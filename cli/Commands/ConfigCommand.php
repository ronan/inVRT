<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'config',
    description: 'View the inVRT configuration',
    help: 'Displays the current inVRT project configuration.',
)]
class ConfigCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        $config = $this->runner->getConfig();

        $io->writeln('# Current inVRT Configuration:');
        $io->writeln('');

        foreach ($config as $key => $value) {
            $io->writeln($key . ': ' . $value, OutputInterface::VERBOSITY_NORMAL);
        }

        return Command::SUCCESS;
    }
}
