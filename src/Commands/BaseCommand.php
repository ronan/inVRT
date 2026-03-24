<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use App\Service\EnvironmentService;

abstract class BaseCommand extends Command
{
    protected EnvironmentService $environment;

    /**
     * Subclasses must implement this to return the bash script name to execute
     */
    abstract protected function getScriptName(): string;

    protected function configure(): void
    {
        $this->addOption('profile', 'p', InputOption::VALUE_OPTIONAL, 'Profile name', 'anonymous')
             ->addOption('device', 'd', InputOption::VALUE_OPTIONAL, 'Device type', 'desktop')
             ->addOption('environment', 'e', InputOption::VALUE_OPTIONAL, 'Environment name', 'local');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $profile = $input->getOption('profile');
        $device = $input->getOption('device');
        $environment = $input->getOption('environment');

        // Initialize environment service
        $this->environment = new EnvironmentService($profile, $device, $environment);
        $env = $this->environment->initialize($output, true);

        // Handle login if credentials exist
        $this->handleLogin($output, $env);

        // Execute the script
        return $this->executeScript($this->getScriptName(), $env);
    }

    /**
     * Handle login if credentials are configured
     */
    protected function handleLogin(OutputInterface $output, array $env): void
    {
        $username = $env['INVRT_USERNAME'] ?? '';
        $password = $env['INVRT_PASSWORD'] ?? '';
        $url = $env['INVRT_URL'] ?? '';

        if ($username || $password) {
            loginIfCredentialsExist($username, $password, $url, $env['INVRT_COOKIES_FILE']);
        }
    }

    /**
     * Execute a bash script with environment variables
     */
    protected function executeScript(string $scriptName, array $env): int
    {
        $cmd = 'bash ' . escapeshellarg($this->joinPath(__DIR__ . '/..', $scriptName));

        // Prepare environment variables for subprocess
        $envStr = '';
        foreach ($env as $key => $value) {
            $envStr .= $key . '=' . escapeshellarg((string)$value) . ' ';
        }

        $exitCode = null;
        passthru($envStr . $cmd, $exitCode);

        return $exitCode ?? Command::SUCCESS;
    }

    /**
     * Join file path segments
     */
    protected function joinPath(...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
