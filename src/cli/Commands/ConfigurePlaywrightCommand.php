<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'configure-playwright',
    description: 'Write the Playwright configuration file to the crawl directory',
    help: 'Copies the bundled playwright.config.ts to INVRT_PLAYWRIGHT_CONFIG_FILE.',
    hidden: true,
)]
class ConfigurePlaywrightCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        return $this->runner->configurePlaywright() === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
