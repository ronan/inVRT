<?php

namespace App\Commands;

use App\Input\InvrtInput;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'crawl',
    description: 'Crawl the website and generate screenshots',
    help: 'Crawls the configured website and generates screenshots for the specified profile, device, and environment.',
)]
class CrawlCommand extends BaseCommand
{
    public function __invoke(SymfonyStyle $io, #[MapInput] InvrtInput $opts): int
    {
        $result = $this->boot($opts, $io);
        if (is_int($result)) {
            return $result;
        }

        $url = $result['INVRT_URL'];
        $dataDir = $result['INVRT_DATA_DIR'];
        $maxDepth = getenv('INVRT_MAX_CRAWL_DEPTH') ?: '3';
        $maxPages = getenv('INVRT_MAX_PAGES') ?: '100';

        if (empty($dataDir)) {
            $io->error('INVRT_DATA_DIR must be set and not empty');
            return Command::FAILURE;
        }

        $io->writeln(
            "🕸️ Crawling '{$result['INVRT_ENVIRONMENT']}' environment ($url) with profile: '{$result['INVRT_PROFILE']}' to depth: $maxDepth, max pages: $maxPages",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        foreach (["$dataDir/clone", "$dataDir/logs"] as $dir) {
            $this->clearDirectory($dir);
        }

        $domain = parse_url($url, PHP_URL_HOST) ?? '';
        [$cookieFile, $cookieHeader] = $this->resolveCookieOption($io, $result['INVRT_COOKIES_FILE'], $dataDir);
        $excludeUrls = $this->resolveExcludeUrls($io, $result['INVRT_DIRECTORY']);

        $exitCode = $this->runWget($io, $url, $domain, $dataDir, $maxDepth, $excludeUrls, $cookieFile, $cookieHeader);
        if ($exitCode !== Command::SUCCESS) {
            return $exitCode;
        }

        $count = $this->parseUrlsFromLog("$dataDir/logs/crawl.log", $url, "$dataDir/crawled_urls.txt");

        $io->writeln("Crawling completed. Found $count unique paths. Results saved to $dataDir/crawled_urls.txt", OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }

    /**
     * Build and execute the wget crawl command.
     */
    private function runWget(
        SymfonyStyle $io,
        string $url,
        string $domain,
        string $dataDir,
        string $maxDepth,
        string $excludeUrls,
        string $cookieFile,
        string $cookieHeader,
    ): int {
        $args = array_values(array_filter([
            "--level=$maxDepth",
            "--exclude-directories=$excludeUrls",
            "--domains=$domain",
            "--directory-prefix=$dataDir/clone",
            $cookieFile !== '' ? "--load-cookies=$cookieFile" : null,
            $cookieHeader !== '' ? "--header=Cookie: $cookieHeader" : null,
            '--recursive',
            '--max-redirect=3',
            '--user-agent=invrt/crawler',
            '--ignore-length',
            '--no-verbose',
            '--no-check-certificate',
            '--reject=css,js,woff,jpg,png,gif,svg',
            '--no-host-directories',
            '--execute',
            'robots=off',
            $url,
        ]));

        $cmd = 'wget ' . implode(' ', array_map('escapeshellarg', $args))
            . ' 2> ' . escapeshellarg("$dataDir/logs/crawl.log");

        exec($cmd, $stdout, $exitCode);

        if ($stdout !== []) {
            $io->writeln(implode("\n", $stdout), OutputInterface::VERBOSITY_VERBOSE);
        }

        return $exitCode;
    }

    /**
     * Parse crawled URLs from the wget log, sort and deduplicate, write to output file.
     * Returns the number of unique paths found.
     */
    private function parseUrlsFromLog(string $logFile, string $baseUrl, string $outputFile): int
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

        file_put_contents($outputFile, implode("\n", $paths) . (count($paths) > 0 ? "\n" : ''));

        return count($paths);
    }

    /**
     * Resolve wget cookie arguments from INVRT_COOKIE env var or cookies file.
     * Returns [cookieFilePath, rawCookieHeaderValue] — at most one will be non-empty.
     *
     * @return array{string, string}
     */
    private function resolveCookieOption(SymfonyStyle $io, string $cookiesFile, string $dataDir): array
    {
        $rawCookie = getenv('INVRT_COOKIE') ?: '';

        if ($rawCookie !== '') {
            $io->writeln('Using provided cookie for crawling.', OutputInterface::VERBOSITY_VERBOSE);
            return ['', $rawCookie];
        }

        if (file_exists("$cookiesFile.txt")) {
            $io->writeln("Using cookies from file: $cookiesFile.txt", OutputInterface::VERBOSITY_VERBOSE);
            return ["$cookiesFile.txt", ''];
        }

        $io->writeln('No cookie provided. Crawling without authentication.', OutputInterface::VERBOSITY_VERBOSE);
        touch("$dataDir/cookies.txt");
        return ['', ''];
    }

    /**
     * Load URL exclusions from exclude_urls.txt, falling back to sensible defaults.
     */
    private function resolveExcludeUrls(SymfonyStyle $io, string $invrtDir): string
    {
        $excludeFile = "$invrtDir/exclude_urls.txt";

        if (!file_exists($excludeFile)) {
            $defaults = '/files,/sites,/user/logout';
            $io->writeln("No exclude_urls.txt found at $excludeFile. Excluding defaults: $defaults", OutputInterface::VERBOSITY_VERBOSE);
            return $defaults;
        }

        $lines = file($excludeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_values(array_filter($lines, fn($l) => !str_starts_with(ltrim($l), '#')));
        $excludeUrls = implode(',', $lines);
        $io->writeln("Excluding URLs: $excludeUrls", OutputInterface::VERBOSITY_VERBOSE);
        return $excludeUrls;
    }

    /**
     * Create dir if absent, or remove all contents if present.
     */
    private function clearDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
    }
}
