<?php

namespace App\Commands;

use App\Input\InvrtInput;
use App\Service\ConfigurationService;
use App\Service\LoginService;
use Symfony\Component\Config\Exception\FileLocatorFileNotFoundException;
use Symfony\Component\Console\Command\Command;
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
     * @return array<string, string>|int
     */
    protected function boot(InvrtInput $opts, SymfonyStyle $io): int
    {
        try {
            $this->resolveConfig($opts);
        } catch (FileLocatorFileNotFoundException $e) {
            if ($this->requiresConfig) {
                $io->writeln('# Configuration file not found at: ' . getenv('INVRT_CONFIG_FILE'));
                $io->writeln("# Run '<comment>invrt init</comment>' to create a new configuration.");
            }
            return Command::FAILURE;
        } catch (\Exception $e) {
            if ($this->requiresConfig) {
                $io->writeln('# Error reading config file at: `' . getenv('INVRT_CONFIG_FILE') . '`');
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

        // If there's a config file load it. Otherwise throws and Exception.
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
        $process = Process::fromShellCommandline(
            'node ' . escapeshellarg(__DIR__ . '/../backstop.js') . ' ' . $mode,
            null,
            $env,
        );
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
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

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
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
