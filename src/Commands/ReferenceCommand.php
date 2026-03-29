<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
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
        $result = $this->boot($opts, $io);
        if (is_int($result)) {
            return $result;
        }

        $io->writeln(
            "📸 Capturing references from '{$result['INVRT_ENVIRONMENT']}' environment ({$result['INVRT_URL']}) with profile: '{$result['INVRT_PROFILE']}' and device: '{$result['INVRT_DEVICE']}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        return $this->runBackstop('reference', $result, $io);
    }
}
