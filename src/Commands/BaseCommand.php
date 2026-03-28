<?php

namespace App\Commands;

use App\Service\EnvironmentService;
use App\Service\LoginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

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
        $loginResult = $this->handleLogin($output, $env);
        if ($loginResult !== Command::SUCCESS) {
            return $loginResult;
        }

        // Execute the script
        return $this->executeScript($this->getScriptName(), $env, $output);
    }

    /**
     * Handle login if credentials are configured
     *
     * @return int Command::SUCCESS on success, Command::FAILURE on error
     */
    protected function handleLogin(OutputInterface $output, array $env): int
    {
        $username = $env['INVRT_USERNAME'] ?? '';
        $password = $env['INVRT_PASSWORD'] ?? '';
        $url = $env['INVRT_URL'] ?? '';
        $cookiesFile = $env['INVRT_COOKIES_FILE'] ?? '';

        return LoginService::loginIfCredentialsExist($username, $password, $url, $cookiesFile, $output);
    }

    /**
     * Execute a bash script with environment variables
     */
    protected function executeScript(string $scriptName, array $env, OutputInterface $output): int
    {
        $cmd = 'bash ' . escapeshellarg($this->joinPath(__DIR__ . '/..', $scriptName));

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $output->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
    }

    /**
     * Join file path segments
     */
    protected function joinPath(...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
