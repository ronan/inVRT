<?php

namespace App\Commands;

use App\Input\InvrtInput;
use InVRT\Core\Configuration;
use InVRT\Core\Runner;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

abstract class BaseCommand
{
    /** Directory containing backstop.js / playwright-login.js. */
    private const APP_DIR = __DIR__ . '/../../src';

    /** When true, boot() attempts login before returning. */
    protected bool $requiresLogin = true;

    protected Runner $runner;

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
            fn($k) => str_starts_with($k, 'INVRT_'),
            ARRAY_FILTER_USE_KEY,
        );

        $filepath = $this->resolveConfigFilepath($env + $processEnv);

        try {
            $config = new Configuration($filepath, $env + $processEnv);
        } catch (\Exception $e) {
            $io->writeln('# Error reading config file at: `' . $filepath . '`', OutputInterface::VERBOSITY_QUIET);
            $io->writeln('[debug] Config read exception: ' . $e->getMessage(), OutputInterface::VERBOSITY_DEBUG);
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

        $this->runner = new Runner($config, realpath(self::APP_DIR) ?: self::APP_DIR, $logger);

        if ($this->requiresLogin) {
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
}
