<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

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

        [
            $INVRT_ENVIRONMENT,
            $INVRT_URL,
            $INVRT_PROFILE,
            $INVRT_CRAWL_DIR,
            $INVRT_CRAWL_LOG,
            $INVRT_CRAWL_FILE,
            $INVRT_CLONE_DIR,
            $INVRT_MAX_CRAWL_DEPTH,
            $INVRT_MAX_PAGES,
            $INVRT_EXCLUDE_FILE,
        ] = array_fill(0, 10, '');
        extract($this->config, EXTR_IF_EXISTS);

        $filesystem = new Filesystem();
        $this->config['INVRT_CRAWL_LOG'] && $filesystem->dumpFile($this->config['INVRT_CRAWL_LOG'], '');
        if ($this->config['INVRT_CRAWL_FILE'] && $filesystem->exists($this->config['INVRT_CRAWL_FILE'])) {
            $filesystem->remove($this->config['INVRT_CRAWL_FILE']);
        }

        if (empty($INVRT_URL)) {
            $io->error('INVRT_URL must be set');
            return Command::FAILURE;
        }

        if (empty($INVRT_CRAWL_DIR)) {
            $io->error('INVRT_CRAWL_DIR must be set');
            return Command::FAILURE;
        }

        $io->writeln(
            "🕸️ Crawling '$INVRT_ENVIRONMENT' environment ($INVRT_URL) with profile: '$INVRT_PROFILE' to depth: $INVRT_MAX_CRAWL_DEPTH, max pages: $INVRT_MAX_PAGES",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        foreach ([$INVRT_CLONE_DIR, dirname($INVRT_CRAWL_LOG)] as $dir) {
            $this->prepareDirectory($dir);
        }

        $args = array_values(array_filter([
            $this->resolveExcludeWGETArg($io, $INVRT_EXCLUDE_FILE),
            $this->resolveCookieWGETArg($io),
            "--level=$INVRT_MAX_CRAWL_DEPTH",
            "--domains=" . (parse_url($INVRT_URL, PHP_URL_HOST) ?? ''),
            "--directory-prefix=$INVRT_CLONE_DIR",
            '--recursive',
            '--max-redirect=3',
            '--user-agent=invrt/crawler',
            '--ignore-length',
            '--no-verbose',
            '--no-check-certificate',
            '--reject=css,js,woff,jpg,png,gif,svg,ico,pdf,ppt,pptx,doc,docx,xls,xlsx',
            '--reject-regex=(edit|devel|delete|logout|webform|files|file|login|register)',
            '--no-host-directories',
            '--execute',
            'robots=off',
            $INVRT_URL,
        ]));

        $cmd = 'wget ' . implode(' ', array_map('escapeshellarg', $args))
            . ' 2> ' . escapeshellarg($INVRT_CRAWL_LOG);

        $io->writeLn("Running command: \n wget " . implode("\\\n  ", array_map('escapeshellarg', $args)), OutputInterface::VERBOSITY_DEBUG);

        exec($cmd, $stdout, $exitCode);

        $stdout && $io->writeln($stdout, OutputInterface::VERBOSITY_NORMAL);

        if ($exitCode !== Command::SUCCESS) {
            $io->writeln("There were errors during the crawl. See logs at $INVRT_CRAWL_LOG", OutputInterface::VERBOSITY_QUIET);
            $io->writeln("Crawl exit code: $exitCode", OutputInterface::VERBOSITY_QUIET);
        }

        $paths = $this->parseUrlsFromLog($INVRT_CRAWL_LOG, $INVRT_URL);
        $count = count($paths);

        file_put_contents($INVRT_CRAWL_FILE, implode("\n", $paths));

        if ($count === 0) {
            $io->writeln('No usable URLs were found during crawl. See crawl log details below:', OutputInterface::VERBOSITY_NORMAL);
            $this->writeCrawlLogTail($io, $INVRT_CRAWL_LOG);
            return Command::FAILURE;
        }

        $io->writeln("Crawling completed. Found $count unique paths. Results saved to $INVRT_CRAWL_FILE", OutputInterface::VERBOSITY_NORMAL);

        return Command::SUCCESS;
    }

    /**
     * Parse crawled URLs from the wget log, sort and deduplicate, then return unique paths.
     *
     * @return list<string>
     */
    private function parseUrlsFromLog(string $logFile, string $baseUrl): array
    {
        $lines = file_exists($logFile)
            ? (file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            : [];

        $marker = "URL:$baseUrl";
        $paths = [];

        foreach ($lines as $line) {
            if (!str_contains($line, $marker)) {
                continue;
            }
            // Extract path: everything after "URL:<baseUrl>" up to the next whitespace
            $rest = substr($line, strpos($line, $marker) + strlen($marker));
            $path = strtok($rest, " \t");
            if ($path !== false) {
                $paths[] = $path;
            }
        }

        $paths = array_unique($paths);
        sort($paths);

        return $paths;
    }

    private function writeCrawlLogTail(SymfonyStyle $io, string $logFile, int $lineCount = 5): void
    {
        if (!is_readable($logFile)) {
            $io->writeln("Unable to read crawl log at $logFile", OutputInterface::VERBOSITY_NORMAL);
            return;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lines === []) {
            $io->writeln('Crawl log is empty.', OutputInterface::VERBOSITY_NORMAL);
            return;
        }

        $io->writeln("Last $lineCount lines of crawl log:", OutputInterface::VERBOSITY_NORMAL);
        foreach (array_slice($lines, -$lineCount) as $line) {
            $io->writeln($line, OutputInterface::VERBOSITY_NORMAL);
        }
    }

    /**
     * Resolve wget cookie arguments from INVRT_COOKIE env var or cookies file.
     * Returns a wget --header=Cookie argument string if INVRT_COOKIE is set, or empty string if no cookie is available.
     *
     * @return string
     */
    private function resolveCookieWGETArg(SymfonyStyle $io): string
    {
        if ($rawCookie = $this->config('INVRT_COOKIE')) {
            $io->writeln('Using provided cookie for crawling.', OutputInterface::VERBOSITY_VERBOSE);
            return "--header=Cookie: $rawCookie";
        }

        $cookie_txt = $this->config('INVRT_COOKIES_FILE') . ".txt";
        if (file_exists($cookie_txt)) {
            $io->writeln("Using cookies from file: $cookie_txt", OutputInterface::VERBOSITY_VERBOSE);
            return "--load-cookies=$cookie_txt";
        }

        $io->writeln('No cookie provided. Crawling without authentication.', OutputInterface::VERBOSITY_VERBOSE);
        touch($cookie_txt);
        return '';
    }

    /**
     * Load URL exclusions from exclude_urls.txt, falling back to sensible defaults.
     */
    private function resolveExcludeWGETArg(SymfonyStyle $io, string $excludeFile): string
    {
        if (!file_exists($excludeFile)) {
            $defaults = '/user/*';
            $io->writeln("No exclude_urls.txt found at $excludeFile. Excluding defaults: $defaults", OutputInterface::VERBOSITY_VERBOSE);
            return "--exclude-directories=$defaults";
        }

        $lines = file($excludeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_values(array_filter($lines, fn($l) => !str_starts_with(ltrim($l), '#')));
        $excludeUrls = implode(',', $lines);
        $io->writeln("Excluding URLs: $excludeUrls", OutputInterface::VERBOSITY_VERBOSE);
        return "--exclude-directories=$excludeUrls";
    }
}
