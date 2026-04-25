<?php

namespace App\Commands;

use App\Input\InitInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize a new inVRT project in the current directory',
    help: 'Initializes a new inVRT project, saving the provided URL into a fresh config file.',
)]
class InitCommand extends BaseCommand
{
    protected bool $requiresLogin = false;

    public function __invoke(InputInterface $input, SymfonyStyle $io, #[MapInput] InitInput $opts): int
    {
        // init never requires a config file to exist
        if (($result = $this->boot($opts, $io, requiresConfig: false)) !== Command::SUCCESS) {
            return $result;
        }

        $url = $this->resolveInitUrl($input, $io, $opts->url);
        if ($url === null) {
            return Command::FAILURE;
        }

        $exitCode = $this->runner->init($url);

        if ($exitCode !== 0) {
            return Command::FAILURE;
        }

        $io->success('InVRT successfully initialized!');

        if ($opts->skipBaseline) {
            return Command::SUCCESS;
        }

        // Re-boot now that plan.yaml has been written so the runner has INVRT_URL.
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        $io->writeln('🏁 Running baseline to capture initial screenshots...');
        $exitCode = $this->runner->baseline();

        return $exitCode === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
