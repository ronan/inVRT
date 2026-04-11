<?php

namespace App\Commands;

use App\Input\InvrtInput;
use App\Service\ConfigurationService;
use App\Service\LoginService;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

abstract class BaseCommand
{
    protected array $config;

    // If the config file is missing the command will warn and fail.
    protected bool $requiresConfig = true;

    // If credentials are available the command will attempt login before running.
    protected bool $requiresLogin = true;

    public function __construct(protected readonly ConfigurationService $configurationService) {}

    /**
     * Initialize environment + login.
     * Returns the resolved INVRT_* env array on success, or an exit code int on failure.
     *
     * @return int
     */
    protected function boot(InvrtInput $opts, SymfonyStyle $io): int
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

        try {
            $this->resolveConfig($opts);

            $io->writeln(
                sprintf(
                    '[debug] Resolved config (config=%s, data_dir=%s, url=%s)',
                    $this->config['INVRT_CONFIG_FILE'] ?? '(not set)',
                    $this->config['INVRT_DATA_DIR'] ?? '(not set)',
                    $this->config['INVRT_URL'] ?? '(not set)',
                ),
                OutputInterface::VERBOSITY_DEBUG,
            );
        } catch (FileLocatorFileNotFoundException $e) {
            if ($this->requiresConfig) {
                $io->writeln('# Configuration file not found at: ' . getenv('INVRT_CONFIG_FILE'), OutputInterface::VERBOSITY_QUIET);
                $io->writeln("# Run '<comment>invrt init</comment>' to create a new configuration.", OutputInterface::VERBOSITY_QUIET);
                $io->writeln('[debug] Config resolution exception: ' . $e->getMessage(), OutputInterface::VERBOSITY_DEBUG);
            }
            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($this->requiresConfig) {
                $io->writeln('# Error reading config file at: `' . getenv('INVRT_CONFIG_FILE') . '`', OutputInterface::VERBOSITY_QUIET);
                $io->writeln('[debug] Config read exception: ' . $e->getMessage(), OutputInterface::VERBOSITY_DEBUG);
            }
            return Command::FAILURE;
        }

        if ($this->requiresLogin) {
            return $this->loginIfNeeded($this->config, $io);
        }

        return Command::SUCCESS;
    }

    /**
     * Resolve the configuration by loading the given / environment / profile / device options
     * and merging them with the defaults, environment variables, and persistent configuration
     * file, setting the resulting resolved configuration array into $this->config.
     *
     * @param InvrtInput $opts
     * @return void
     * @throws \Symfony\Component\Config\Exception\FileLocatorFileNotFoundException
     * @throws \Exception
     */

    protected function resolveConfig(InvrtInput $opts): void
    {
        // Load the default config and environment settings
        $this->config = $this->configurationService->options($opts->environment, $opts->profile, $opts->device);

        // If there's a config file load it. Otherwise throws an Exception.
        $this->config = $this->configurationService->load();
    }

    /**
     * Attempt to login using credentials from the resolved environment variables if they exist.
     * Throws a RuntimeException if login fails.
     *
     * @param array<string, string> $env
     * @param SymfonyStyle $io
     * @return int
     * @throws \RuntimeException
     */
    protected function loginIfNeeded(array $env, SymfonyStyle $io): int
    {
        $io->writeln(
            sprintf(
                '[debug] Login pre-check (username=%s, has_password=%s, cookies_file=%s)',
                empty($env['INVRT_USERNAME']) ? 'no' : 'yes',
                empty($env['INVRT_PASSWORD']) ? 'no' : 'yes',
                $env['INVRT_COOKIES_FILE'] ?? '(not set)',
            ),
            OutputInterface::VERBOSITY_DEBUG,
        );

        $result = LoginService::loginIfCredentialsExist(
            $env['INVRT_USERNAME'] ?? '',
            $env['INVRT_PASSWORD'] ?? '',
            $env['INVRT_URL'] ?? '',
            $env['INVRT_COOKIES_FILE'] ?? '',
            $io,
        );
        return $result;
    }

    /**
     * Run backstop.js in the given mode (e.g. 'reference' or 'test').
     *
     * @param array<string, string> $env
     */
    protected function runBackstop(string $mode, array $env, SymfonyStyle $io): int
    {
        $cmd = 'node ' . escapeshellarg(__DIR__ . '/../backstop.js') . ' ' . $mode;
        $io->writeln('[debug] Running BackstopJS command: ' . $cmd, OutputInterface::VERBOSITY_DEBUG);

        $process = Process::fromShellCommandline(
            $cmd,
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        $exitCode = $process->getExitCode() ?? Command::SUCCESS;
        $io->writeln('[debug] BackstopJS exit code: ' . $exitCode, OutputInterface::VERBOSITY_DEBUG);

        return $exitCode;
    }

    /**
     * Execute a bash script with the resolved environment variables.
     *
     * @param string $scriptName
     * @param array<string, string> $env
     * @param SymfonyStyle $io
     * @return int
     */
    protected function executeScript(string $scriptName, array $env, SymfonyStyle $io): int
    {
        $cmd = 'bash ' . escapeshellarg(Path::join(__DIR__ . '/..', $scriptName));
        $io->writeln('[debug] Running script: ' . $cmd, OutputInterface::VERBOSITY_DEBUG);

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        $exitCode = $process->getExitCode() ?? Command::SUCCESS;
        $io->writeln('[debug] Script exit code: ' . $exitCode, OutputInterface::VERBOSITY_DEBUG);

        return $exitCode;
    }

    /**
     * Create dir if absent, or remove all contents if present.
     */
    protected function prepareDirectory(string $dir): void
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

    /**
     * Get a resolved configuration value by key from the loaded configuration array, returning
     * the provided default if the key is not set.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     * @throws \RuntimeException if the configuration has not been loaded yet
     */
    protected function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }
}
