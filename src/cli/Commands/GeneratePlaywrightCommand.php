<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'generate-playwright',
    description: 'Generate a Playwright spec from crawled URLs',
    help: 'Reads the crawled URL list and writes a Playwright TypeScript spec to scripts/playwright.spec.ts.',
    hidden: true,
)]
class GeneratePlaywrightCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->generatePlaywright() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
