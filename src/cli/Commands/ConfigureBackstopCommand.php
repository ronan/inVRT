<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'configure-backstop',
    description: 'Configure BackstopJS for visual regression testing',
    help: 'Generates or updates the BackstopJS configuration for the specified profile, device, and environment.',
    hidden: true,
)]
class ConfigureBackstopCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->configureBackstop() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
