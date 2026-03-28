<?php

namespace App\Commands;

use App\Input\InvrtInput;
use App\Service\EnvironmentService;
use App\Service\LoginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class BaseCommand
{
    /**
     * Initialises the environment and handles login.
     * Returns the resolved INVRT_* env array on success, or an exit code int on failure.
     *
     * @return array<string, string>|int
     */
    protected function boot(InvrtInput $opts, SymfonyStyle $io, bool $requiresConfig = true): array|int
    {
        $env = (new EnvironmentService($opts->profile, $opts->device, $opts->environment))
            ->initialize($io, $requiresConfig);

        $loginResult = LoginService::loginIfCredentialsExist(
            $env['INVRT_USERNAME'] ?? '',
            $env['INVRT_PASSWORD'] ?? '',
            $env['INVRT_URL'] ?? '',
            $env['INVRT_COOKIES_FILE'] ?? '',
            $io,
        );

        if ($loginResult !== Command::SUCCESS) {
            return $loginResult;
        }

        return $env;
    }

    /**
     * Execute a bash script with the resolved environment variables.
     */
    protected function executeScript(string $scriptName, array $env, SymfonyStyle $io): int
    {
        $cmd = 'bash ' . escapeshellarg($this->joinPath(__DIR__ . '/..', $scriptName));

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
    }

    protected function joinPath(string ...$segments): string
    {
        return implode(DIRECTORY_SEPARATOR, $segments);
    }
}
