<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new inVRT project in the current directory',
    help: 'Initializes a new inVRT project with the default configuration structure.',
)]
class InitCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        // init never requires a config file to exist
        if (($result = $this->boot($opts, $io, requiresConfig: false)) !== Command::SUCCESS) {
            return $result;
        }

        $exitCode = $this->runner->init();

        if ($exitCode === 0) {
            $io->success('InVRT successfully initialized!');
        }

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
