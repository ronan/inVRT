<?php

namespace App\Commands;

use App\Input\InvrtInput;
use InVRT\Core\Configuration;
use InVRT\Core\Runner;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StreamableInputInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand
{
    /** Directory containing backstop.js / playwright-login.js. */
    private const APP_DIR = __DIR__ . '/../../js';

    /** When true, boot() attempts login before returning. */
    protected bool $requiresLogin = true;

    protected Runner $runner;
    protected Configuration $config;

    /**
     * Resolve configuration, export env vars, optionally login, and populate $this->runner.
     * Returns Command::SUCCESS on success, or Command::FAILURE on error.
     */
    protected function boot(InvrtInput $opts, SymfonyStyle $io, bool $requiresConfig = true): int
    {
        $io->writeln(
            sprintf(
                '[debug] Bootstrapping command (environment=%s, profile=%s, device=%s)',
                $opts->environment,
                $opts->profile,
                $opts->device,
            ),
            OutputInterface::VERBOSITY_DEBUG,
        );

        $env = array_filter([
            'INVRT_ENVIRONMENT' => $opts->environment,
            'INVRT_PROFILE'     => $opts->profile,
            'INVRT_DEVICE'      => $opts->device,
            'INVRT_CWD'         => getenv('INVRT_CWD') ?: getcwd(),
        ], fn($v) => $v !== false && $v !== '');

        // Merge any existing INVRT_* env vars from the process environment
        $processEnv = array_filter(
            getenv(),
            fn($v, $k) => str_starts_with($k, 'INVRT_') && $v !== '',
            ARRAY_FILTER_USE_BOTH,
        );

        $filepath = $this->resolveConfigFilepath($env + $processEnv);

        try {
            $config = new Configuration($filepath, $env + $processEnv);
        } catch (\Exception $e) {
            $io->writeln('# Error reading config file at: `' . $filepath . '`', OutputInterface::VERBOSITY_QUIET);
            $io->writeln('[debug] Config read exception: ' . $e->getMessage(), OutputInterface::VERBOSITY_NORMAL);
            return Command::FAILURE;
        }

        if ($requiresConfig && !$config->fileExists()) {
            $io->writeln('# Configuration file not found at: ' . $filepath, OutputInterface::VERBOSITY_QUIET);
            $io->writeln("# Run '<comment>invrt init</comment>' to create a new configuration.", OutputInterface::VERBOSITY_QUIET);
            return Command::FAILURE;
        }

        $config->export();

        $io->writeln(
            sprintf(
                '[debug] Resolved config (config=%s, url=%s)',
                $config->get('INVRT_CONFIG_FILE', '(not set)'),
                $config->get('INVRT_URL', '(not set)'),
            ),
            OutputInterface::VERBOSITY_DEBUG,
        );

        $logger = new ConsoleLogger($io, [
            LogLevel::ERROR   => OutputInterface::VERBOSITY_QUIET,
            LogLevel::WARNING => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::NOTICE  => OutputInterface::VERBOSITY_NORMAL,
            LogLevel::INFO    => OutputInterface::VERBOSITY_VERBOSE,
            LogLevel::DEBUG   => OutputInterface::VERBOSITY_DEBUG,
        ]);

        $this->config = $config;
        $this->runner = new Runner($config, realpath(self::APP_DIR) ?: self::APP_DIR, $logger);

        if ($this->requiresLogin && ($config->fileExists() || $requiresConfig)) {
            $result = $this->runner->login();
            if ($result !== 0) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    private function resolveConfigFilepath(array $env): string
    {
        // Check for an explicit INVRT_CONFIG_FILE override first
        if (!empty($env['INVRT_CONFIG_FILE'])) {
            return $env['INVRT_CONFIG_FILE'];
        }

        // Derive the default path: <cwd>/.invrt/config.yaml
        $cwd = $env['INVRT_CWD'] ?? getcwd() ?: '';
        if (!empty($env['INVRT_DIRECTORY'])) {
            return rtrim($env['INVRT_DIRECTORY'], '/') . '/config.yaml';
        }

        return rtrim($cwd, '/') . '/.invrt/config.yaml';
    }

    protected function bootOrInitialize(InputInterface $input, InvrtInput $opts, SymfonyStyle $io, ?string $url = null): int
    {
        if (($result = $this->boot($opts, $io, requiresConfig: false)) !== Command::SUCCESS) {
            return $result;
        }

        if ($this->config->fileExists()) {
            return Command::SUCCESS;
        }

        $io->note('No configuration file found. Initializing inVRT first.');

        $resolvedUrl = $this->resolveInitUrl($input, $io, $url);
        if ($resolvedUrl === null) {
            return Command::FAILURE;
        }

        if ($this->runner->init($resolvedUrl) !== 0) {
            return Command::FAILURE;
        }

        return $this->boot($opts, $io);
    }

    protected function resolveInitUrl(InputInterface $input, SymfonyStyle $io, ?string $url = null): ?string
    {
        $candidate = trim((string) ($url ?? $this->config->get('INVRT_URL', '')));

        if ($candidate === '') {
            if (!$this->canPromptForUrl($input)) {
                $io->error('A URL is required to initialize inVRT.');
                return null;
            }

            $candidate = trim((string) $io->ask('What URL should inVRT use?'));
        }

        if ($candidate === '') {
            $io->error('A URL is required to initialize inVRT.');
            return null;
        }

        if (filter_var($candidate, FILTER_VALIDATE_URL) === false) {
            $io->error('The URL must be an absolute URL such as https://example.com.');
            return null;
        }

        return rtrim($candidate, '/');
    }

    private function canPromptForUrl(InputInterface $input): bool
    {
        if (!$input->isInteractive()) {
            return false;
        }

        if ($input instanceof StreamableInputInterface) {
            return true;
        }

        return function_exists('stream_isatty') ? stream_isatty(STDIN) : true;
    }
}
