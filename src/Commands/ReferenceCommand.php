<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
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
        if ($this->boot($opts, $io) !== Command::SUCCESS) {
            return Command::FAILURE;
        }

        [
            $INVRT_ENVIRONMENT,
            $INVRT_URL,
            $INVRT_PROFILE,
            $INVRT_CAPTURE_DIR,
            $INVRT_DEVICE,
        ] = array_fill(0, 10, '');
        extract($this->config, EXTR_IF_EXISTS);

        $io->writeln(
            "📸 Capturing references from '{$INVRT_ENVIRONMENT}' environment ({$INVRT_URL}) with profile: '{$INVRT_PROFILE}' and device: '{$INVRT_DEVICE}'",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        if (($crawlValidation = $this->validateCrawledUrls($io)) !== Command::SUCCESS) {
            return $crawlValidation;
        }

        $this->prepareDirectory($INVRT_CAPTURE_DIR);

        return $this->runBackstop('reference', $this->config, $io);
    }

    private function validateCrawledUrls(SymfonyStyle $io): int
    {
        $crawlFile = $this->config['INVRT_CRAWL_FILE'] ?? '';
        if ($crawlFile === '' || !is_readable($crawlFile)) {
            $io->writeln('No crawled URLs file found. Run `invrt crawl` first.', OutputInterface::VERBOSITY_NORMAL);
            return Command::FAILURE;
        }

        $lines = file($crawlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            $io->writeln('No crawled URLs are available. Crawl has run but found no usable URLs.', OutputInterface::VERBOSITY_NORMAL);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
