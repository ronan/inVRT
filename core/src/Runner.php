<?php

namespace InVRT\Core;

use InVRT\Core\Service\LoginService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

/**
 * Core orchestration layer for inVRT operations.
 *
 * All exit codes follow Unix conventions: 0 = success, non-zero = failure.
 */
class Runner
{
    public function __construct(
        private readonly Configuration $config,
        private readonly string $appDir,
        private readonly LoggerInterface $logger,
    ) {}

    // -------------------------------------------------------------------------
    // Public commands
    // -------------------------------------------------------------------------

    /** Initialize a new inVRT project directory with default config. */
    public function init(): int
    {
        $cwd        = $this->config->get('INVRT_CWD', '');
        $directory  = $this->config->get('INVRT_DIRECTORY', '');
        $configFile = $this->config->get('INVRT_CONFIG_FILE', '');

        if (empty($cwd)) {
            $this->logger->error("⚠️  I can't make a directory here because I don't know where I am.");
            return 1;
        }

        if (is_dir($directory)) {
            $this->logger->error('⚠️  InVRT is already initialized for this project. Please remove the .invrt directory (' . $directory . ') if you want to re-initialize.');
            return 1;
        }

        $this->logger->notice('🚀 Initializing InVRT for the project at ' . $cwd);

        if (!mkdir($directory, 0755, true)) {
            $this->logger->error('Failed to create invrt directory at ' . $directory);
            return 1;
        }
        $this->logger->info('✓ Created invrt directory at ' . $directory);

        if (!mkdir(Path::join($directory, 'data'), 0755, true)) {
            $this->logger->error('Failed to create data directory');
            return 1;
        }

        if (!mkdir(Path::join($directory, 'scripts'), 0755, true)) {
            $this->logger->error('Failed to create scripts directory');
            return 1;
        }
        $this->logger->info('✓ Created data directories for generated data, and user scripts.');

        $configContent = <<<'YAML'
# InVRT Configuration File
# This file is used to store configuration settings for InVRT.
# You can customize the settings below as needed.

name: My InVRT Project

environments:
  local:
    name: Local
    url: http://localhost

  dev:
    name: Development
    url: https://dev.example.com

  prod:
    name: Production
    url: https://prod.example.com

profiles:
  anonymous:
    name: Anonymous Visitor Profile
    description: Test the site as an anonymous visitor with no special permissions.

  admin:
    name: Admin Profile
    description: A profile with admin privileges.
    username: admin
    password: password123

devices:
  desktop:
    name: Desktop Viewport
    description: A desktop sized viewport for testing.
    viewport_width: 1920
    viewport_height: 1080

  mobile:
    name: Mobile Viewport
    description: A viewport for mobile testing.
    viewport_width: 375
    viewport_height: 667
YAML;

        if (file_put_contents($configFile, $configContent) === false) {
            $this->logger->error('Failed to create config.yaml');
            return 1;
        }
        $this->logger->info('✓ Initialized InVRT configuration file at ' . $configFile);

        $excludeUrls = "/user/logout\n/files\n/sites\n/core\n";
        $excludePath = Path::join($directory, 'exclude_urls.txt');
        if (file_put_contents($excludePath, $excludeUrls) === false) {
            $this->logger->error('Failed to create exclude_urls.txt');
            return 1;
        }

        $this->logger->notice('✅ InVRT successfully initialized!');
        return 0;
    }

    /** Returns the resolved configuration for display or inspection. */
    public function getConfig(): array
    {
        return $this->config->all();
    }

    /** Crawl the target URL and write unique paths to the crawl file. */
    public function crawl(): int
    {
        $env     = $this->config->all();
        $url     = $env['INVRT_URL']       ?? '';
        $crawlDir = $env['INVRT_CRAWL_DIR'] ?? '';

        if (empty($url)) {
            $this->logger->error('INVRT_URL must be set');
            return 1;
        }

        if (empty($crawlDir)) {
            $this->logger->error('INVRT_CRAWL_DIR must be set');
            return 1;
        }

        $crawlLog  = $env['INVRT_CRAWL_LOG']  ?? '';
        $crawlFile = $env['INVRT_CRAWL_FILE'] ?? '';
        $cloneDir  = $env['INVRT_CLONE_DIR']  ?? '';
        $maxDepth  = $env['INVRT_MAX_CRAWL_DEPTH'] ?? 3;
        $maxPages  = $env['INVRT_MAX_PAGES']       ?? 100;
        $excludeFile = $env['INVRT_EXCLUDE_FILE']  ?? '';
        $profile     = $env['INVRT_PROFILE']       ?? '';
        $environment = $env['INVRT_ENVIRONMENT']   ?? '';

        $filesystem = new Filesystem();
        $crawlLog  && $filesystem->dumpFile($crawlLog, '');
        if ($crawlFile && $filesystem->exists($crawlFile)) {
            $filesystem->remove($crawlFile);
        }

        $this->logger->info("🕸️ Crawling '$environment' environment ($url) with profile: '$profile' to depth: $maxDepth, max pages: $maxPages");

        foreach ([$cloneDir, dirname($crawlLog)] as $dir) {
            $this->prepareDirectory($dir);
        }

        $args = array_values(array_filter([
            $this->resolveExcludeArg($excludeFile),
            $this->resolveCookieArg($env),
            "--level=$maxDepth",
            '--domains=' . (parse_url($url, PHP_URL_HOST) ?? ''),
            "--directory-prefix=$cloneDir",
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
            $url,
        ]));

        $cmd = 'wget ' . implode(' ', array_map('escapeshellarg', $args))
            . ' 2> ' . escapeshellarg($crawlLog);

        $this->logger->debug("Running command: \n wget " . implode("\\\n  ", array_map('escapeshellarg', $args)));

        exec($cmd, $stdout, $exitCode);

        $stdout && $this->logger->notice(implode("\n", $stdout));

        if ($exitCode !== 0) {
            $this->logger->warning("There were errors during the crawl. See logs at $crawlLog");
            $this->logger->warning("Crawl exit code: $exitCode");
        }

        $paths = self::parseUrlsFromLog($crawlLog, $url);
        $count = count($paths);

        file_put_contents($crawlFile, implode("\n", $paths));

        if ($count === 0) {
            $this->logger->notice('No usable URLs were found during crawl. See crawl log details below:');
            $this->logCrawlLogTail($crawlLog);
            return 1;
        }

        $this->logger->notice("Crawling completed. Found $count unique paths. Results saved to $crawlFile");
        return 0;
    }

    /** Capture reference screenshots, running a crawl first if needed. */
    public function reference(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';
        $captureDir  = $env['INVRT_CAPTURE_DIR'] ?? '';
        $crawlFile   = $env['INVRT_CRAWL_FILE']  ?? '';

        $this->logger->info("📸 Capturing references from '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        if ($crawlFile === '' || !is_readable($crawlFile)) {
            $this->logger->notice('🕸️ No crawled URLs found — running crawl first.');
            if (($result = $this->crawl()) !== 0) {
                return $result;
            }
        }

        if (($result = $this->validateCrawledUrls()) !== 0) {
            return $result;
        }

        $this->prepareDirectory($captureDir);

        return $this->runBackstop('reference', $env);
    }

    /** Run visual regression tests, capturing references first if needed. */
    public function test(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';
        $captureDir  = $env['INVRT_CAPTURE_DIR'] ?? '';

        $this->logger->notice("🔬 Testing '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        if ($this->referencesAreMissing($captureDir)) {
            $this->logger->notice('📸 No reference screenshots found — capturing references first.');
            if (($result = $this->runBackstop('reference', $env)) !== 0) {
                return $result;
            }
        }

        return $this->runBackstop('test', $env);
    }

    /** Attempt login using credentials from the resolved config. */
    public function login(): int
    {
        $env = $this->config->all();

        $this->logger->debug(sprintf(
            'Login pre-check (username=%s, has_password=%s, cookies_file=%s)',
            empty($env['INVRT_USERNAME']) ? 'no' : 'yes',
            empty($env['INVRT_PASSWORD']) ? 'no' : 'yes',
            $env['INVRT_COOKIES_FILE'] ?? '(not set)',
        ));

        return LoginService::loginIfCredentialsExist(
            $env['INVRT_USERNAME']    ?? '',
            $env['INVRT_PASSWORD']    ?? '',
            $env['INVRT_URL']         ?? '',
            $env['INVRT_COOKIES_FILE'] ?? '',
            $this->appDir,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Public static helpers
    // -------------------------------------------------------------------------

    /**
     * Parse crawled URLs from a wget log file.
     *
     * @return list<string>
     */
    public static function parseUrlsFromLog(string $logFile, string $baseUrl): array
    {
        $lines = file_exists($logFile)
            ? (file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [])
            : [];

        $marker = "URL:$baseUrl";
        $paths  = [];

        foreach ($lines as $line) {
            if (!str_contains($line, $marker)) {
                continue;
            }
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

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function runBackstop(string $mode, array $env): int
    {
        $script = rtrim($this->appDir, '/') . '/backstop.js';
        $cmd    = 'node ' . escapeshellarg($script) . ' ' . $mode;
        $this->logger->debug('Running BackstopJS command: ' . $cmd);

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(function (mixed $type, mixed $buffer): void {
            print($buffer);
        });

        $exitCode = $process->getExitCode() ?? 0;
        $this->logger->debug('BackstopJS exit code: ' . $exitCode);

        return $exitCode;
    }

    /** Create dir if absent, or clear contents if present. */
    private function prepareDirectory(string $dir): void
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

    private function validateCrawledUrls(): int
    {
        $crawlFile = $this->config->get('INVRT_CRAWL_FILE', '');
        if ($crawlFile === '' || !is_readable($crawlFile)) {
            $this->logger->notice('No crawled URLs file found. Run `invrt crawl` first.');
            return 1;
        }

        $lines = file($crawlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            $this->logger->notice('No crawled URLs are available. Crawl has run but found no usable URLs.');
            return 1;
        }

        return 0;
    }

    private function referencesAreMissing(string $captureDir): bool
    {
        $refDir = $captureDir . '/bitmaps/reference';
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

    private function resolveCookieArg(array $env): string
    {
        if ($rawCookie = ($env['INVRT_COOKIE'] ?? '')) {
            $this->logger->info('Using provided cookie for crawling.');
            return "--header=Cookie: $rawCookie";
        }

        $cookieTxt = ($env['INVRT_COOKIES_FILE'] ?? '') . '.txt';
        if (file_exists($cookieTxt)) {
            $this->logger->info("Using cookies from file: $cookieTxt");
            return "--load-cookies=$cookieTxt";
        }

        $this->logger->info('No cookie provided. Crawling without authentication.');
        touch($cookieTxt);
        return '';
    }

    private function resolveExcludeArg(string $excludeFile): string
    {
        if (!file_exists($excludeFile)) {
            $defaults = '/user/*';
            $this->logger->info("No exclude_urls.txt found at $excludeFile. Excluding defaults: $defaults");
            return "--exclude-directories=$defaults";
        }

        $lines = file($excludeFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $lines = array_values(array_filter($lines, fn($l) => !str_starts_with(ltrim($l), '#')));
        $excludeUrls = implode(',', $lines);
        $this->logger->info("Excluding URLs: $excludeUrls");
        return "--exclude-directories=$excludeUrls";
    }

    private function logCrawlLogTail(string $logFile, int $lineCount = 5): void
    {
        if (!is_readable($logFile)) {
            $this->logger->notice("Unable to read crawl log at $logFile");
            return;
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES);
        if ($lines === false || $lines === []) {
            $this->logger->notice('Crawl log is empty.');
            return;
        }

        $this->logger->notice("Last $lineCount lines of crawl log:");
        foreach (array_slice($lines, -$lineCount) as $line) {
            $this->logger->notice($line);
        }
    }
}
