<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'reference',
    description: 'Create reference screenshots for comparison',
    help: 'Creates reference screenshots for the specified profile, device, and environment to use as baseline for comparison.',
)]
class ReferenceCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        $result = $this->boot($opts, $io);
        if (\is_int($result)) {
            return $result;
        }

        $env = $result;

        $io->writeln(
            "📸 Capturing references from '{$env['INVRT_ENVIRONMENT']}' environment ({$env['INVRT_URL']}) with profile: '{$env['INVRT_PROFILE']}' and device: '{$env['INVRT_DEVICE']}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $process = Process::fromShellCommandline('node ' . escapeshellarg($env['INVRT_SCRIPTS_DIR'] . '/backstop.js') . ' reference', null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
    }
}
