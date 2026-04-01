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
        if (($result = $this->boot($opts, $io)) !== Command::SUCCESS) {
            return $result;
        }

        [
            $INVRT_ENVIRONMENT,
            $INVRT_URL,
            $INVRT_PROFILE,
            $INVRT_DEVICE,
            $INVRT_CAPTURE_DIR,
        ] = array_fill(0, 5, '');
        extract($this->config, EXTR_IF_EXISTS);

        $io->writeln(
            "🔬 Testing '{$INVRT_ENVIRONMENT}' environment ({$INVRT_URL}) with profile: '{$INVRT_PROFILE}' and device: '{$INVRT_DEVICE}'",
            OutputInterface::VERBOSITY_NORMAL,
        );

        if ($this->referencesAreMissing($INVRT_CAPTURE_DIR)) {
            $io->writeln(
                '📸 No reference screenshots found — capturing references first.',
                OutputInterface::VERBOSITY_NORMAL,
            );
            $refResult = $this->runBackstop('reference', $this->config, $io);
            if ($refResult !== Command::SUCCESS) {
                return $refResult;
            }
        }

        return $this->runBackstop('test', $this->config, $io);
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
