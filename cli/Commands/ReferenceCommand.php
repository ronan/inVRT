<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'reference',
    description: 'Create reference screenshots for comparison',
    help: 'Creates reference screenshots for the specified profile, device, and environment to use as baseline for comparison.',
)]
class ReferenceCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->reference() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
