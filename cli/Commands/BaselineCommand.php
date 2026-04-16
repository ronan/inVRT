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
    description: 'Create or refresh the approved visual baseline',
    help: 'Ensures reference and test artifacts exist, then approves the latest visual results.',
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
