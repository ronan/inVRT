<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'baseline',
    description: 'Capture a fresh baseline from check through approve',
    help: 'Runs the full pipeline — check, crawl, generate-playwright, reference, test, approve — to establish a new approved baseline.',
)]
class BaselineCommand extends BaseCommand
{
    public function __invoke(InputInterface $input, SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->bootOrInitialize($input, $opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->baseline() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
