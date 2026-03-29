<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
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
        $result = $this->boot($opts, $io);
        if (is_int($result)) {
            return $result;
        }

        $io->writeln(
            "🔬 Testing '{$result['INVRT_ENVIRONMENT']}' environment ({$result['INVRT_URL']}) with profile: '{$result['INVRT_PROFILE']}' and device: '{$result['INVRT_DEVICE']}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        if ($this->referencesAreMissing($result['INVRT_DATA_DIR'])) {
            $io->writeln(
                '📸 No reference screenshots found — capturing references first.',
                OutputInterface::VERBOSITY_VERBOSE,
            );
            $refResult = $this->runBackstop('reference', $result, $io);
            if ($refResult !== Command::SUCCESS) {
                return $refResult;
            }
        }

        return $this->runBackstop('test', $result, $io);
    }

    private function referencesAreMissing(string $dataDir): bool
    {
        $refDir = $dataDir . '/bitmaps/reference';
        if (!is_dir($refDir)) {
            return true;
        }
        foreach (new \FilesystemIterator($refDir) as $entry) {
            if ($entry->isFile() && strtolower($entry->getExtension()) === 'png') {
                return false;
            }
        }
        return true;
    }
}
