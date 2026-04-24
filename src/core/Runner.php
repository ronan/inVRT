<?php

namespace InVRT\Core;

use InVRT\Core\Service\Filesystem;
use InVRT\Core\Service\LoginService;
use InVRT\Core\Service\NodeOutputParser;
use InVRT\Core\Service\PlanService;
use InVRT\Core\Service\PlaywrightRunner;
use InVRT\Core\Service\ProjectId;
use InVRT\Core\Service\UrlNormalizer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

/**
 * Core orchestration layer for inVRT operations.
 *
 * All exit codes follow Unix conventions: 0 = success, non-zero = failure.
 *
 * Runner is an orchestrator: each public method maps to a CLI command and
 * dispatches to JS scripts or services. Non-orchestration logic belongs in
 * src/js/*.js or src/core/Service/*.
 */
class Runner
{
    public function __construct(
        private readonly Configuration $config,
        private readonly string $appDir,
        private readonly LoggerInterface $logger,
    ) {}

    /** Initialize a new inVRT project directory with default config. */
    public function init(?string $url = null): int
    {
        $cwd         = $this->config->get('INVRT_CWD');
        $directory   = $this->config->get('INVRT_DIRECTORY');
        $configFile  = $this->config->get('INVRT_CONFIG_FILE');
        $environment = $this->config->get('INVRT_ENVIRONMENT');
        $profile     = $this->config->get('INVRT_PROFILE');
        $device      = $this->config->get('INVRT_DEVICE');
        $excludeFile = $this->config->get('INVRT_EXCLUDE_FILE');
        $planFile    = $this->config->get('INVRT_PLAN_FILE');

        if (empty($cwd)) {
            $this->logger->error("⚠️  I can't make a directory here because I don't know where I am.");
            return 1;
        }

        $url = UrlNormalizer::normalize((string) $url);
        if ($url === '') {
            $this->logger->error('A valid URL is required to initialize inVRT.');
            return 1;
        }

        if (is_dir($directory)) {
            $this->logger->error('⚠️  InVRT is already initialized for this project. Please remove the .invrt directory (' . $directory . ') if you want to re-initialize.');
            return 1;
        }

        $this->logger->notice('🚀 Initializing InVRT for the project at ' . $cwd);

        Filesystem::ensureDir($directory);
        $this->logger->info('✓ Created invrt directory at ' . $directory);

        Filesystem::ensureDir(Path::join($directory, 'data'));
        Filesystem::ensureDir(Path::join($directory, 'scripts'));

        Filesystem::writeFile(
            Path::join($directory, 'scripts', 'onready.ts'),
            "// Runs after the page is ready and before the screenshot is captured.\n",
        );

        $this->logger->info('✓ Created data directories for generated data, and user scripts.');

        $projectId = ProjectId::generate($url);

        $configContent = Yaml::dump([
            'project' => [
                'name' => basename($cwd) ?: 'My InVRT Project',
                'id'   => $projectId,
            ],
            'environments' => [$environment => ['url' => $url]],
            'profiles'     => [$profile     => []],
            'devices'      => [$device      => []],
        ], 4, 2);

        Filesystem::writeFile($configFile, $configContent);
        $this->logger->info('✓ Initialized InVRT configuration file at ' . $configFile);

        if (!empty($excludeFile) && !file_exists($excludeFile)) {
            Filesystem::writeFile($excludeFile, "/logout\n/user/logout\n/files\n/download\n/assets\n/images\n");
        }

        if (!PlanService::update($planFile, $url, $projectId)) {
            $this->logger->error('Failed to create or update plan.yaml at ' . $planFile);
            return 1;
        }
        $this->logger->info('✓ Initialized plan file at ' . $planFile);

        // Seed in-memory config so the immediate check() has project URL + ID.
        $this->config->set('INVRT_URL', $url);
        $this->config->set('INVRT_ID', $projectId);

        $this->logger->notice('✅ InVRT successfully initialized!');

        if ($this->check() !== 0) {
            $this->logger->warning('⚠️  Site check failed. Run `invrt check` manually once the site is reachable.');
        }

        return 0;
    }

    /** Returns a project status summary by delegating to js/info.js. */
    public function info(): array
    {
        [$exit, $stdout] = $this->runNodeCapturing('info.js');
        if ($exit !== 0) {
            return [];
        }
        $decoded = json_decode($stdout, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Returns the resolved configuration for display or inspection. */
    public function getConfig(): array
    {
        return $this->config->all();
    }

    /** Fetch the site homepage, enrich plan.yaml with resolved URL + title. */
    public function check(): int
    {
        $outputFile = $this->config->get('INVRT_CHECK_FILE');
        $result = $this->runNode('check.js', null, $outputFile);
        if ($result !== 0) {
            return $result;
        }

        if (!is_readable($outputFile)) {
            $this->logger->warning('Check completed but no check file was found to enrich plan.yaml.');
            return 0;
        }

        $planFile  = $this->config->get('INVRT_PLAN_FILE');
        $checkData = Yaml::parseFile($outputFile);
        $checkData = is_array($checkData) ? $checkData : [];

        $title = (string) ($checkData['title'] ?? '');
        if (!PlanService::update(
            $planFile,
            (string) $this->config->get('INVRT_URL'),
            (string) $this->config->get('INVRT_ID'),
            $title,
            $title,
        )) {
            $this->logger->error('Failed to update plan.yaml at ' . $planFile);
            return 1;
        }

        $this->logger->debug('Updated plan.yaml with latest check metadata.');
        return 0;
    }

    /** Crawl the target URL and write unique paths to the crawl file. */
    public function crawl(): int
    {
        return $this->runNode('crawl.js', null, $this->config->get('INVRT_CRAWL_FILE'));
    }

    /** Capture reference screenshots, running a crawl first if needed. */
    public function reference(): int
    {
        $url         = $this->config->get('INVRT_URL');
        $profile     = $this->config->get('INVRT_PROFILE');
        $device      = $this->config->get('INVRT_DEVICE');
        $environment = $this->config->get('INVRT_ENVIRONMENT');

        $this->logger->info("📸 Capturing references from '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        if (!$this->planHasPages()) {
            $this->logger->notice('🕸️ No planned pages found — running crawl first.');
            if (($result = $this->crawl()) !== 0) {
                return $result;
            }
            if (!$this->planHasPages()) {
                $this->logger->notice('No pages are available. Crawl has run but found no usable URLs.');
                return 1;
            }
        }

        if (($result = $this->generatePlaywright()) !== 0) {
            return $result;
        }

        return (new PlaywrightRunner($this->config, $this->logger))->run('reference');
    }

    /** Run visual regression tests, capturing references first if needed. */
    public function test(): int
    {
        $url         = $this->config->get('INVRT_URL');
        $profile     = $this->config->get('INVRT_PROFILE');
        $device      = $this->config->get('INVRT_DEVICE');
        $environment = $this->config->get('INVRT_ENVIRONMENT');

        $this->logger->notice("🔬 Testing '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        $referenceFile = $this->config->get('INVRT_REFERENCE_FILE');
        if (!file_exists($referenceFile)) {
            $this->logger->notice('📸 No reference screenshots found — capturing references first.');
            if (($result = $this->reference()) !== 0) {
                return $result;
            }
        } elseif (($result = $this->generatePlaywright()) !== 0) {
            return $result;
        }

        return (new PlaywrightRunner($this->config, $this->logger))->run('test');
    }

    /** Approve the latest results by re-running Playwright with --update-snapshots. */
    public function approve(): int
    {
        $url         = $this->config->get('INVRT_URL');
        $profile     = $this->config->get('INVRT_PROFILE');
        $device      = $this->config->get('INVRT_DEVICE');
        $environment = $this->config->get('INVRT_ENVIRONMENT');

        $this->logger->notice("✅ Approving latest results for '$environment' environment ($url) with profile: '$profile' and device: '$device'");

        return (new PlaywrightRunner($this->config, $this->logger))->run('reference');
    }

    /** Full baseline workflow: check → crawl → generate-playwright → reference → test → approve. */
    public function baseline(): int
    {
        foreach (['check', 'crawl'] as $step) {
            if (($r = $this->$step()) !== 0) {
                return $r;
            }
        }
        if (($r = $this->generatePlaywright()) !== 0) {
            return $r;
        }

        $playwright = new PlaywrightRunner($this->config, $this->logger);
        if (($r = $playwright->run('reference')) !== 0) {
            return $r;
        }
        if (($r = $playwright->run('test')) !== 0) {
            return $r;
        }

        return $this->approve();
    }

    /** Generate or regenerate the BackstopJS configuration from plan.yaml. */
    public function configureBackstop(): int
    {
        return $this->runNode(
            'backstop-config.js',
            $this->config->get('INVRT_PLAN_FILE'),
            $this->config->get('INVRT_BACKSTOP_CONFIG_FILE'),
        );
    }

    /** Write the bundled playwright.config.ts via js/configure-playwright.js. */
    public function configurePlaywright(): int
    {
        return $this->runNode('configure-playwright.js');
    }

    /** Generate a Playwright TypeScript spec from plan.yaml pages. */
    public function generatePlaywright(): int
    {
        if (($result = $this->configurePlaywright()) !== 0) {
            return $result;
        }

        return $this->runNode(
            'generate-playwright.js',
            $this->config->get('INVRT_PLAN_FILE'),
            $this->config->get('INVRT_PLAYWRIGHT_SPEC_FILE'),
        );
    }

    /** Attempt login using credentials from the resolved config. */
    public function login(): int
    {
        $this->logger->debug(sprintf(
            'Login pre-check (username=%s, has_password=%s, cookies_file=%s)',
            empty($this->config->get('INVRT_USERNAME')) ? 'no' : 'yes',
            empty($this->config->get('INVRT_PASSWORD')) ? 'no' : 'yes',
            $this->config->get('INVRT_COOKIES_FILE') ?: '(not set)',
        ));

        return LoginService::loginIfCredentialsExist(
            (string) $this->config->get('INVRT_USERNAME'),
            (string) $this->config->get('INVRT_PASSWORD'),
            (string) $this->config->get('INVRT_URL'),
            (string) $this->config->get('INVRT_COOKIES_FILE'),
            $this->appDir,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------

    /**
     * Run a Node.js script from the app's JS directory.
     *
     * Streams $inputFile content to stdin if provided. Captures stdout and
     * writes it to $outputFile if provided. Log messages arrive on stderr as
     * pino NDJSON and are routed to the PSR-3 logger.
     */
    private function runNode(string $script, ?string $inputFile = null, ?string $outputFile = null): int
    {
        [$exit, $stdout] = $this->runNodeCapturing($script, $inputFile);

        if ($outputFile !== null && $outputFile !== '') {
            $this->logger->debug("Writing output to $outputFile");
            Filesystem::writeFile($outputFile, $stdout);
        }

        return $exit;
    }

    /**
     * Run a Node.js script and return [exitCode, stdout].
     *
     * @return array{0: int, 1: string}
     */
    private function runNodeCapturing(string $script, ?string $inputFile = null): array
    {
        $file = rtrim($this->appDir, '/') . '/' . $script;
        $cmd  = 'node ' . escapeshellarg($file);
        $this->logger->debug("Running Node script: $cmd");

        $process = Process::fromShellCommandline($cmd, null, $this->config->all());
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

        return [$process->getExitCode() ?? 0, $stdout];
    }

    /**
     * Does plan.yaml contain at least one testable page path?
     *
     * @phpstan-impure
     */
    private function planHasPages(): bool
    {
        $planFile = $this->config->get('INVRT_PLAN_FILE');
        if (!$planFile || !is_readable($planFile)) {
            return false;
        }
        $parsed = Yaml::parseFile($planFile);
        $pages  = is_array($parsed) ? ($parsed['pages'] ?? []) : [];
        if (!is_array($pages)) {
            return false;
        }
        foreach (array_keys($pages) as $key) {
            if (is_string($key) && str_starts_with($key, '/')) {
                return true;
            }
        }
        return false;
    }
}
