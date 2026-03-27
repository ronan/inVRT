<?php

namespace App\Commands;

use App\Service\EnvironmentService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CrawlCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('crawl')
            ->setDescription('Crawl the website and generate screenshots')
            ->setHelp('Crawls the configured website and generates screenshots for the specified profile, device, and environment.');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->environment = new EnvironmentService(
            $input->getOption('profile'),
            $input->getOption('device'),
            $input->getOption('environment'),
        );
        $env = $this->environment->initialize($output, true);

        $loginResult = $this->handleLogin($output, $env);
        if ($loginResult !== Command::SUCCESS) {
            return $loginResult;
        }

        $url = $env['INVRT_URL'];
        $dataDir = $env['INVRT_DATA_DIR'];
        $maxDepth = getenv('INVRT_MAX_CRAWL_DEPTH') ?: '3';
        $maxPages = getenv('INVRT_MAX_PAGES') ?: '100';

        $output->writeln(
            "🕸️ Crawling '{$env['INVRT_ENVIRONMENT']}' environment ($url) with profile: '{$env['INVRT_PROFILE']}' to depth: $maxDepth, max pages: $maxPages",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        foreach (["$dataDir/clone", "$dataDir/logs"] as $dir) {
            $this->clearDirectory($dir);
        }

        $domain = parse_url($url, PHP_URL_HOST) ?? '';
        [$cookieFile, $cookieHeader] = $this->resolveCookieOption($output, $env['INVRT_COOKIES_FILE'], $dataDir);
        $excludeUrls = $this->resolveExcludeUrls($output, $env['INVRT_DIRECTORY']);

        $exitCode = $this->runWget($output, $url, $domain, $dataDir, $maxDepth, $excludeUrls, $cookieFile, $cookieHeader);
        if ($exitCode !== Command::SUCCESS) {
            return $exitCode;
        }

        $count = $this->parseUrlsFromLog("$dataDir/logs/crawl.log", $url, "$dataDir/crawled_urls.txt");

        $output->writeln("Crawling completed. Found $count unique paths. Results saved to $dataDir/crawled_urls.txt", OutputInterface::VERBOSITY_VERBOSE);

        return Command::SUCCESS;
    }

    /**
     * Build and execute the wget crawl command.
     */
    private function runWget(
        OutputInterface $output,
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
            $output->writeln(implode("\n", $stdout), OutputInterface::VERBOSITY_VERBOSE);
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
    private function resolveCookieOption(OutputInterface $output, string $cookiesFile, string $dataDir): array
    {
        $rawCookie = getenv('INVRT_COOKIE') ?: '';

        if ($rawCookie !== '') {
            $output->writeln('Using provided cookie for crawling.', OutputInterface::VERBOSITY_VERBOSE);
            return ['', $rawCookie];
        }

        if (file_exists("$cookiesFile.txt")) {
            $output->writeln("Using cookies from file: $cookiesFile.txt", OutputInterface::VERBOSITY_VERBOSE);
            return ["$cookiesFile.txt", ''];
        }

        $output->writeln('No cookie provided. Crawling without authentication.', OutputInterface::VERBOSITY_VERBOSE);
        touch("$dataDir/cookies.txt");
        return ['', ''];
    }

    /**
     * Load URL exclusions from exclude_urls.txt, falling back to sensible defaults.
     */
    private function resolveExcludeUrls(OutputInterface $output, string $invrtDir): string
    {
        $excludeFile = "$invrtDir/exclude_urls.txt";

        if (!file_exists($excludeFile)) {
            $defaults = '/files,/sites,/user/logout';
            $output->writeln("No exclude_urls.txt found at $excludeFile. Excluding defaults: $defaults", OutputInterface::VERBOSITY_VERBOSE);
            return $defaults;
        }

        $lines = file($excludeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_values(array_filter($lines, fn($l) => !str_starts_with(ltrim($l), '#')));
        $excludeUrls = implode(',', $lines);
        $output->writeln("Excluding URLs: $excludeUrls", OutputInterface::VERBOSITY_VERBOSE);
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

    protected function getScriptName(): string
    {
        return '';
    }
}
