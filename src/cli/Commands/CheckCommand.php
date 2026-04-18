<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'check',
    description: 'Check site connectivity and collect metadata',
    help: 'Fetches the site homepage, extracts the page title, detects HTTPS, and records any permanent redirects. Results are written to .invrt/data/<environment>/check.yaml.',
)]
class CheckCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->check() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
