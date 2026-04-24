<?php

namespace InVRT\Core;

use InVRT\Core\Service\LoginService;
use InVRT\Core\Service\NodeOutputParser;
use InVRT\Core\Service\PlanService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

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
    public function init(?string $url = null): int
    {
        $cwd        = $this->config->get('INVRT_CWD', '');
        $directory  = $this->config->get('INVRT_DIRECTORY', '');
        $configFile = $this->config->get('INVRT_CONFIG_FILE', '');
        $environment = $this->config->get('INVRT_ENVIRONMENT', 'local');
        $profile     = $this->config->get('INVRT_PROFILE', 'anonymous');
        $device      = $this->config->get('INVRT_DEVICE', 'desktop');
        $excludeFile = $this->config->get('INVRT_EXCLUDE_FILE');
        $planFile    = $this->config->get('INVRT_PLAN_FILE', '');

        if (empty($cwd)) {
            $this->logger->error("⚠️  I can't make a directory here because I don't know where I am.");
            return 1;
        }

        $url = $this::normalizeURL((string) $url);
        if ($url === '') {
            $this->logger->error('A valid URL is required to initialize inVRT.');
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
        file_put_contents(Path::join($directory, 'scripts', 'onready.js'), '');
        $this->logger->info('✓ Created data directories for generated data, and user scripts.');

        $projectId = self::generateProjectId($url);

        $configContent = Yaml::dump([
            'project' => [
                'name' => basename($cwd) ?: 'My InVRT Project',
                'id'   => $projectId,
            ],
            'environments' => [
                $environment => [
                    'url' => $url,
                ],
            ],
            'profiles' => [
                $profile => [],
            ],
            'devices' => [
                $device => [],
            ],
        ], 4, 2);

        if (file_put_contents($configFile, $configContent) === false) {
            $this->logger->error('Failed to create config.yaml');
            return 1;
        }
        $this->logger->info('✓ Initialized InVRT configuration file at ' . $configFile);

        $excludeUrls = <<<'EOF'
/logout
/user/logout
/files
/download
/assets
/images
EOF;
        if (!empty($excludeFile)) {
            $excludeDir = dirname($excludeFile);
            if (!is_dir($excludeDir)) {
                mkdir($excludeDir, 0755, true);
            }
        }

        if (
            !empty($excludeFile)
            && !file_exists($excludeFile)
            && file_put_contents($excludeFile, $excludeUrls) === false
        ) {
            $this->logger->error('Failed to create exclude_paths.txt');
            return 1;
        }

        if ($planFile === '') {
            $this->logger->error('INVRT_PLAN_FILE is not set.');
            return 1;
        }

        if (!PlanService::update($planFile, $url, $projectId)) {
            $this->logger->error('Failed to create or update plan.yaml at ' . $planFile);
            return 1;
        }
        $this->logger->info('✓ Initialized plan file at ' . $planFile);

        // init runs check() before the command re-boots configuration from disk.
        // Seed the in-memory config so Node check.js receives the URL immediately.
        $this->config->set('INVRT_URL', $url);
        $this->config->set('INVRT_ID', $projectId);

        $this->logger->notice('✅ InVRT successfully initialized!');

        if ($this->check() !== 0) {
            $this->logger->warning('⚠️  Site check failed. Run `invrt check` manually once the site is reachable.');
        }

        return 0;
    }

    /** Returns a project status summary. */
    public function info(): array
    {
        $env         = $this->config->all();
        $crawlFile   = $env['INVRT_CRAWL_FILE']  ?? '';
        $crawlLog    = $env['INVRT_CRAWL_LOG']   ?? '';
        $captureDir  = $env['INVRT_CAPTURE_DIR'] ?? '';
        $device      = $env['INVRT_DEVICE']      ?? 'desktop';
        $environment = $env['INVRT_ENVIRONMENT'] ?? 'local';

        $crawledPages = 0;
        if ($crawlFile !== '' && is_readable($crawlFile)) {
            $lines = file($crawlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $crawledPages = $lines !== false ? count($lines) : 0;
        }

        return [
            'name'         => ($this->config->getSection('project')['name'] ?? '') ?: '',
            'id'           => $env['INVRT_ID'] ?? '',
            'config_file'  => $env['INVRT_CONFIG_FILE'] ?? '',
            'environment'  => $env['INVRT_ENVIRONMENT'] ?? '',
            'profile'      => $env['INVRT_PROFILE']     ?? '',
            'device'       => $env['INVRT_DEVICE']      ?? '',
            'environments' => array_keys((array) ($this->config->getSection('environments') ?? [])),
            'profiles'     => array_keys((array) ($this->config->getSection('profiles')     ?? [])),
            'devices'      => array_keys((array) ($this->config->getSection('devices')      ?? [])),
            'crawled_pages'         => $crawledPages,
            'reference_screenshots' => $this->countScreenshots($captureDir . '/reference/' . $device),
            'test_screenshots'      => $this->countScreenshots($captureDir . '/' . $environment . '/' . $device),
            'crawl_log_tail'        => $this->readLogTail($crawlLog),
        ];
    }

    /** Returns the resolved configuration for display or inspection. */
    public function getConfig(): array
    {
        return $this->config->all();
    }

    /**
     * Fetch the site homepage and write a check.yaml with metadata.
     *
     * Follows redirects, detects HTTPS, extracts the page title, and records
     * the resolved URL. Returns 0 on success, 1 on failure.
     */
    public function check(): int
    {
        $outputFile = $this->config->get('INVRT_CHECK_FILE', '') ?: null;
        $result = $this->runNode('check.js', null, $outputFile);
        if ($result !== 0) {
            return $result;
        }

        if ($outputFile === null || !is_readable($outputFile)) {
            $this->logger->warning('Check completed but no check file was found to enrich plan.yaml.');
            return 0;
        }

        $planFile = $this->config->get('INVRT_PLAN_FILE', '');
        if ($planFile === '') {
            $this->logger->error('INVRT_PLAN_FILE is not set.');
            return 1;
        }

        $checkData = Yaml::parseFile($outputFile);
        if (!is_array($checkData)) {
            $checkData = [];
        }

        $projectUrl = (string) $this->config->get('INVRT_URL', '');
        $projectId = (string) $this->config->get('INVRT_ID', '');
        $projectTitle = (string) ($checkData['title'] ?? '');

        if (!PlanService::update($planFile, $projectUrl, $projectId, $projectTitle, $projectTitle)) {
            $this->logger->error('Failed to update plan.yaml at ' . $planFile);
            return 1;
        }

        $this->logger->debug('Updated plan.yaml with latest check metadata.');
        return 0;
    }

    /** Crawl the target URL and write unique paths to the crawl file. */
    public function crawl(): int
    {
        $outputFile = $this->config->get('INVRT_CRAWL_FILE', '') ?: null;
        return $this->runNode('crawl.js', null, $outputFile);
    }

    /** Capture reference screenshots, running a crawl first if needed. */
    public function reference(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';
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

        if (($result = $this->generatePlaywright()) !== 0) {
            return $result;
        }

        return $this->runPlaywright('reference', $env);
    }

    /** Run visual regression tests, capturing references first if needed. */
    public function test(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';

        $this->logger->notice("🔬 Testing '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        if ($this->referencesAreMissing()) {
            $this->logger->notice('📸 No reference screenshots found — capturing references first.');
            // Reuse reference() so first-run prerequisites (crawl + URL validation) are enforced.
            if (($result = $this->reference()) !== 0) {
                return $result;
            }
        }

        return $this->runPlaywright('test', $env);
    }

    /** Approve the latest results by re-running Playwright with --update-snapshots. */
    public function approve(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';

        $this->logger->notice("✅ Approving latest results for '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        return $this->runPlaywright('reference', $env);
    }

    /**
     * Run the full baseline workflow from scratch: check → crawl → generate-playwright → reference → test → approve.
     *
     * Always re-runs every step regardless of prior artifacts.
     */
    public function baseline(): int
    {
        if (($result = $this->check()) !== 0) {
            return $result;
        }

        if (($result = $this->crawl()) !== 0) {
            return $result;
        }

        $env = $this->config->all();

        if (($result = $this->generatePlaywright()) !== 0) {
            return $result;
        }

        if (($result = $this->runPlaywright('reference', $env)) !== 0) {
            return $result;
        }

        if (($result = $this->runPlaywright('test', $env)) !== 0) {
            return $result;
        }

        return $this->approve();
    }

    /** Generate or regenerate the BackstopJS configuration from the crawled URL list. */
    public function configureBackstop(): int
    {
        $inputFile  = $this->config->get('INVRT_CRAWL_FILE', '') ?: null;
        $outputFile = $this->config->get('INVRT_BACKSTOP_CONFIG_FILE', '') ?: null;
        return $this->runNode('backstop-config.js', $inputFile, $outputFile);
    }

    /** Write the bundled playwright.config.ts to INVRT_PLAYWRIGHT_CONFIG_FILE. */
    public function configurePlaywright(): int
    {
        $configFile = $this->config->get('INVRT_PLAYWRIGHT_CONFIG_FILE', '');
        if ($configFile === '') {
            $this->logger->error('INVRT_PLAYWRIGHT_CONFIG_FILE is not set.');
            return 1;
        }

        $dir = dirname($configFile);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            $this->logger->error('Failed to create directory for playwright config: ' . $dir);
            return 1;
        }

        $content = <<<'TS'
import { defineConfig } from '@playwright/test';

/**
 * See https://playwright.dev/docs/test-configuration.
 */
export default defineConfig({
  outputDir: 'results',
  snapshotPathTemplate: 'reference/{arg}{ext}',
  reporter: [['html', { outputFolder: 'report' }]],
  use: {
    screenshot: 'on',
  }
});
TS;

        if (file_put_contents($configFile, $content) === false) {
            $this->logger->error('Failed to write playwright config to ' . $configFile);
            return 1;
        }

        $this->logger->info('✓ Wrote playwright config to ' . $configFile);
        return 0;
    }

    /** Generate a Playwright TypeScript spec from plan.yaml pages. */
    public function generatePlaywright(): int
    {
        if (($result = $this->configurePlaywright()) !== 0) {
            return $result;
        }

        $inputFile  = $this->config->get('INVRT_PLAN_FILE', '') ?: null;
        $outputFile = $this->config->get('INVRT_PLAYWRIGHT_SPEC_FILE', '') ?: null;
        return $this->runNode('generate-playwright.js', $inputFile, $outputFile);
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

    /** Generate a stable, short project identifier from project URL + random seed. */
    public static function generateProjectId(string $url): string
    {
        return self::encodeId($url, random_int(0, 0xFFFF));
    }

    /** Generate a stable, short identifier from a string and optional seed/salt. */
    public static function encodeId(string $value, int $seed = 0): string
    {
        $hash = (int) hexdec(hash('crc32b', $value));
        $alphabet = 'swxdyktzhgjfblrpmcqvn';
        $number = (($hash & 0xFFFFFFFF) << 16) | ($seed & 0xFFFF);
        $base = strlen($alphabet);

        if ($number === 0) {
            return $alphabet[0];
        }

        $encoded = '';
        while ($number > 0) {
            $index = $number % $base;
            $encoded = $alphabet[$index] . $encoded;
            $number = intdiv($number, $base);
        }

        return $encoded;
    }

    /** Normalize a URL by ensuring it has a scheme, host, and properly formatted components. */
    public function normalizeURL(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return '';
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'http';
        $host   = isset($parts['host']) ? strtolower($parts['host']) : '';
        $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path   = $parts['path'] ?? '';
        $query  = isset($parts['query']) ? '?' . $parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        return $scheme . '://' . $host . $port . $path . $query . $fragment;
    }


    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Run a Node.js script from the app's JS directory.
     *
     * Streams $inputFile content to the process stdin if provided. Captures
     * stdout and writes it to $outputFile if provided. Log messages arrive on
     * stderr as pino NDJSON and are routed to the PSR-3 logger.
     */
    private function runNode(string $script, ?string $inputFile = null, ?string $outputFile = null): int
    {
        $env  = $this->config->all();
        $file = rtrim($this->appDir, '/') . '/' . $script;
        $cmd  = 'node ' . escapeshellarg($file);
        $this->logger->debug("Running Node script: $cmd");

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);

        if ($inputFile !== null && is_readable($inputFile)) {
            $process->setInput(file_get_contents($inputFile));
        }

        $parser = new NodeOutputParser($this->logger);
        $stdout = '';
        $process->run(function (mixed $type, mixed $buffer) use ($parser, &$stdout): void {
            if ($type === Process::ERR) {
                $parser->write($buffer);
            } else {
                $stdout .= $buffer;
            }
        });
        $parser->flush();

        if ($outputFile !== null) {
            $dir = dirname($outputFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $this->logger->debug("Writing output to $outputFile");
            file_put_contents($outputFile, $stdout);
        }

        return $process->getExitCode() ?? 0;
    }

    /**
     * Run Playwright tests for the given mode using the resolved config file.
     *
     * mode 'reference' — runs with --update-snapshots to capture/update baseline.
     * mode 'test'      — runs normally and compares against stored snapshots.
     */
    private function runPlaywright(string $mode, array $env): int
    {
        $configFile = $this->config->get('INVRT_PLAYWRIGHT_CONFIG_FILE', '');
        $specFile   = $this->config->get('INVRT_PLAYWRIGHT_SPEC_FILE', '');
        $configDir  = $configFile !== '' ? dirname($configFile) : '';

        $cmd = 'npx playwright test';
        if ($configFile !== '') {
            $cmd .= ' --config=' . escapeshellarg($configFile);
        }
        if ($specFile !== '') {
            $cmd .= ' ' . escapeshellarg($specFile);
        }
        if ($mode === 'reference') {
            $cmd .= ' --update-snapshots';
        }

        $this->logger->debug('Running Playwright command: ' . $cmd);
        $this->logger->notice('Running playwright test' . ($mode === 'reference' ? ' --update-snapshots' : ''));

        $process = Process::fromShellCommandline($cmd, $configDir ?: null, $env);
        $process->setTimeout(null);

        $parser = new NodeOutputParser($this->logger);
        $output = '';
        $process->run(function (mixed $type, mixed $buffer) use ($parser, &$output): void {
            if ($type === Process::ERR) {
                $parser->write($buffer);
            } else {
                $output .= $buffer;
                // Forward stdout lines as notices
                foreach (explode("\n", $buffer) as $line) {
                    $line = trim($line);
                    if ($line !== '') {
                        $this->logger->notice($line);
                    }
                }
            }
        });
        $parser->flush();

        $exitCode = $process->getExitCode() ?? 0;
        $this->logger->debug('Playwright exit code: ' . $exitCode);

        $this->writeResultsFile($mode === 'reference' ? 'reference' : 'test', $output . $parser->getMessages());

        return $exitCode;
    }

    /** Write output to the appropriate results file for the given mode. */
    private function writeResultsFile(string $mode, string $output): void
    {
        $file = match ($mode) {
            'reference' => $this->config->get('INVRT_REFERENCE_FILE', ''),
            'test'      => $this->config->get('INVRT_TEST_FILE', ''),
            default     => '',
        };

        if ($file === '') {
            return;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, $output);
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

    private function referencesAreMissing(): bool
    {
        $file = $this->config->get('INVRT_REFERENCE_FILE', '');
        return $file === '' || !file_exists($file);
    }

    /** Count PNG files recursively in a directory. */
    private function countScreenshots(string $dir): int
    {
        if (!is_dir($dir)) {
            return 0;
        }

        $count = 0;
        $iter  = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'png') {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Read the last N lines of a log file.
     *
     * @return list<string>
     */
    private function readLogTail(string $logFile, int $lineCount = 5): array
    {
        if (!is_readable($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            return [];
        }

        return array_values(array_slice($lines, -$lineCount));
    }

}
