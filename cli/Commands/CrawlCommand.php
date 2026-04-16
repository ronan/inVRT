<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'crawl',
    description: 'Crawl the website and generate screenshots',
    help: 'Crawls the configured website and generates screenshots for the specified profile, device, and environment.',
)]
class CrawlCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->crawl() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
