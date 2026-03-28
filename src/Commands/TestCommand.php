<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test',
    description: 'Run visual regression tests',
    help: 'Runs visual regression tests comparing current screenshots against reference screenshots.',
)]
class TestCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        return $this->withEnv($opts, $io, function (array $env) use ($io): int {
            $io->writeln(
                "🔬 Testing '{$env['INVRT_ENVIRONMENT']}' environment ({$env['INVRT_URL']}) with profile: '{$env['INVRT_PROFILE']}' and device: '{$env['INVRT_DEVICE']}'",
                OutputInterface::VERBOSITY_VERBOSE,
            );
            return $this->runBackstop('test', $env, $io);
        });
    }
}
