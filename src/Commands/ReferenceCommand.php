<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
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
        if ($this->boot($opts, $io) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        $io->writeln(
            "📸 Capturing references from '{$this->config['INVRT_ENVIRONMENT']}' environment ({$this->config['INVRT_URL']}) with profile: '{$this->config['INVRT_PROFILE']}' and device: '{$this->config['INVRT_DEVICE']}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        return $this->runBackstop('reference', $this->config, $io);
    }
}
