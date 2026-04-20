<?php

namespace InVRT\Core;

use InVRT\Core\Service\LoginService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
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

        if (empty($cwd)) {
            $this->logger->error("⚠️  I can't make a directory here because I don't know where I am.");
            return 1;
        }

        $url = trim((string) $url);
        if ($url === '') {
            $this->logger->error('A URL is required to initialize inVRT.');
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

        $configContent = Yaml::dump([
            'name' => basename($cwd) ?: 'My InVRT Project',
            'settings' => [],
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
/downloads
EOF;
        if (
            !empty($excludeFile) && 
            !file_exists($excludeFile) &&
            file_put_contents($excludeFile, $excludeUrls) === false
          ) {
            $this->logger->error('Failed to create exclude_paths.txt');
            return 1;
        }

        $this->logger->notice('✅ InVRT successfully initialized!');

        if ($this->check() !== 0) {
            $this->logger->warning('⚠️  Site check failed. Run `invrt check` manually once the site is reachable.');
        }

        return 0;
    }

    /** Returns a project status summary. */
    public function info(): array
    {
        $env        = $this->config->all();
        $crawlFile  = $env['INVRT_CRAWL_FILE']  ?? '';
        $crawlLog   = $env['INVRT_CRAWL_LOG']   ?? '';
        $captureDir = $env['INVRT_CAPTURE_DIR'] ?? '';

        $crawledPages = 0;
        if ($crawlFile !== '' && is_readable($crawlFile)) {
            $lines = file($crawlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $crawledPages = $lines !== false ? count($lines) : 0;
        }

        return [
            'name'         => $this->config->getSection('name') ?? '',
            'config_file'  => $env['INVRT_CONFIG_FILE'] ?? '',
            'environment'  => $env['INVRT_ENVIRONMENT'] ?? '',
            'profile'      => $env['INVRT_PROFILE']     ?? '',
            'device'       => $env['INVRT_DEVICE']      ?? '',
            'environments' => array_keys((array) ($this->config->getSection('environments') ?? [])),
            'profiles'     => array_keys((array) ($this->config->getSection('profiles')     ?? [])),
            'devices'      => array_keys((array) ($this->config->getSection('devices')      ?? [])),
            'crawled_pages'         => $crawledPages,
            'reference_screenshots' => $this->countScreenshots($captureDir . '/bitmaps/reference'),
            'test_screenshots'      => $this->countScreenshots($captureDir . '/bitmaps/test'),
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
        $env       = $this->config->all();
        $url       = $env['INVRT_URL']        ?? '';
        $checkFile = $env['INVRT_CHECK_FILE'] ?? '';

        if ($url === '') {
            $this->logger->error('INVRT_URL must be set');
            return 1;
        }

        if ($checkFile === '') {
            $this->logger->error('INVRT_CHECK_FILE must be set');
            return 1;
        }

        $this->logger->info("🔍 Checking site at $url");

        $ch = curl_init();
        if ($ch === false) {
            $this->logger->error('curl_init() failed — curl extension may not be available');
            return 1;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => $env['INVRT_USER_AGENT'] ?? 'InVRT/1.0',
        ]);

        $body     = curl_exec($ch);
        $errno    = curl_errno($ch);
        $errMsg   = curl_error($ch);
        $info     = curl_getinfo($ch);

        if ($errno !== 0 || $body === false) {
            $this->logger->error("Failed to connect to $url: $errMsg");
            return 1;
        }

        $finalUrl      = (string) ($info['url']            ?? $url);
        $redirectCount = (int) ($info['redirect_count']  ?? 0);

        // Detect whether a permanent redirect was followed by re-requesting with no follow.
        $redirectedFrom = null;
        if ($redirectCount > 0) {
            $firstCode = $this->getInitialHttpCode($url);
            if ($firstCode === 301) {
                $redirectedFrom = rtrim($url, '/');
            }
        }

        $title   = $this->extractTitle((string) $body);
        $isHttps = str_starts_with($finalUrl, 'https://');

        $data = array_filter([
            'url'             => rtrim($finalUrl, '/'),
            'title'           => $title,
            'https'           => $isHttps,
            'redirected_from' => $redirectedFrom,
            'checked_at'      => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], fn($v) => $v !== null);

        $dir = dirname($checkFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($checkFile, Yaml::dump($data, 2, 2));

        $this->logger->notice("✓ Site check complete. Title: \"$title\". HTTPS: " . ($isHttps ? 'yes' : 'no') . ". Written to $checkFile");

        return 0;
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
        $checkFile   = $env['INVRT_CHECK_FILE']    ?? '';

        $filesystem = new Filesystem();
        $crawlLog  && $filesystem->dumpFile($crawlLog, '');
        if ($crawlFile && $filesystem->exists($crawlFile)) {
            $filesystem->remove($crawlFile);
        }

        $this->logger->info("🕸️ Crawling '$environment' environment ($url) with profile: '$profile' to depth: $maxDepth, max pages: $maxPages");

        if ($checkFile !== '' && !file_exists($checkFile)) {
            $this->logger->notice('🔍 No site check found — running check first.');
            $this->check();
        }

        foreach ([$cloneDir, dirname($crawlLog)] as $dir) {
            $this->logger->info("Preparing directory: $dir");
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

        $this->generateBackstopConfig();

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
        $this->ensureBackstopConfig();

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

        $this->logger->notice("🔬 Testing '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        if ($this->referencesAreMissing()) {
            $this->logger->notice('📸 No reference screenshots found — capturing references first.');
            // Reuse reference() so first-run prerequisites (crawl + URL validation) are enforced.
            if (($result = $this->reference()) !== 0) {
                return $result;
            }
        }

        return $this->runBackstop('test', $env);
    }

    /** Approve the latest BackstopJS test results. */
    public function approve(): int
    {
        $env         = $this->config->all();
        $url         = $env['INVRT_URL']         ?? '';
        $profile     = $env['INVRT_PROFILE']     ?? '';
        $device      = $env['INVRT_DEVICE']      ?? '';
        $environment = $env['INVRT_ENVIRONMENT'] ?? '';

        $this->logger->notice("✅ Approving latest results for '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        return $this->runBackstop('approve', $env);
    }

    /** Create or refresh the approved visual baseline. */
    public function baseline(): int
    {
        if ($this->referencesAreMissing()) {
            $this->logger->notice('📸 No reference screenshots found — capturing references first.');
            if (($result = $this->reference()) !== 0) {
                return $result;
            }
        }

        if ($this->testResultsAreMissing()) {
            $this->logger->notice('🔬 No test screenshots found — running test first.');
            if (($result = $this->test()) !== 0) {
                return $result;
            }
        }

        return $this->approve();
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

    /** Generate the BackstopJS config file from crawled URLs. */
    public function generateBackstopConfig(): int
    {
        $env = $this->config->all();
        $script = rtrim($this->appDir, '/') . '/backstop-config.js';
        $cmd = 'node ' . escapeshellarg($script);
        $this->logger->debug('Generating backstop config: ' . $cmd);

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(function (mixed $type, mixed $buffer): void {
            print($buffer);
        });

        $exitCode = $process->getExitCode() ?? 0;
        if ($exitCode !== 0) {
            $this->logger->warning('Failed to generate backstop config');
        }

        return $exitCode;
    }

    /** Generate backstop config if it doesn't exist yet. */
    private function ensureBackstopConfig(): void
    {
        $configFile = $this->config->get('INVRT_BACKSTOP_CONFIG_FILE', '');
        if ($configFile !== '' && file_exists($configFile)) {
            return;
        }

        $this->generateBackstopConfig();
    }

    private function runBackstop(string $mode, array $env): int
    {
        $script = rtrim($this->appDir, '/') . '/backstop.js';
        $cmd    = 'node ' . escapeshellarg($script) . ' ' . $mode;
        $this->logger->debug('Running BackstopJS command: ' . $cmd);

        $output  = '';
        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(function (mixed $type, mixed $buffer) use (&$output): void {
            print($buffer);
            $output .= $buffer;
        });

        $exitCode = $process->getExitCode() ?? 0;
        $this->logger->debug('BackstopJS exit code: ' . $exitCode);

        $this->writeResultsFile($mode, $output);

        return $exitCode;
    }

    /** Write BackstopJS output to the appropriate results file for the given mode. */
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

    private function referencesAreMissing(): bool
    {
        $file = $this->config->get('INVRT_REFERENCE_FILE', '');
        return $file === '' || !file_exists($file);
    }

    private function testResultsAreMissing(): bool
    {
        $file = $this->config->get('INVRT_TEST_FILE', '');
        return $file === '' || !file_exists($file);
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
            $this->logger->info("No exclude file found at $excludeFile. Excluding defaults: $defaults");
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

    /** Make a single non-following HEAD/GET request and return the HTTP status code. */
    private function getInitialHttpCode(string $url): int
    {
        $ch = curl_init();
        if ($ch === false) {
            return 0;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY         => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        curl_exec($ch);
        $code = (int) (curl_getinfo($ch, CURLINFO_HTTP_CODE) ?? 0);
        return $code;
    }

    /** Extract the text content of the first <title> element in an HTML string. */
    private function extractTitle(string $html): string
    {
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return '';
    }
}
