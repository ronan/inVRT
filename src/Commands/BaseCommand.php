<?php

namespace App\Commands;

use App\Input\InvrtInput;
use App\Service\EnvironmentService;
use App\Service\LoginService;
use App\Support\PathHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

abstract class BaseCommand
{
    use PathHelper;

    /**
     * Initialises environment + login, then calls $callback with the resolved env array.
     * Short-circuits with an exit code if boot fails.
     *
     * @param callable(array<string,string>): int $callback
     */
    protected function withEnv(
        InvrtInput $opts,
        SymfonyStyle $io,
        callable $callback,
        bool $requiresConfig = true,
    ): int {
        $result = $this->boot($opts, $io, $requiresConfig);
        return \is_int($result) ? $result : $callback($result);
    }

    /**
     * Run backstop.js in the given mode (e.g. 'reference' or 'test').
     *
     * @param array<string, string> $env
     */
    protected function runBackstop(string $mode, array $env, SymfonyStyle $io): int
    {
        $process = Process::fromShellCommandline(
            'node ' . escapeshellarg($env['INVRT_SCRIPTS_DIR'] . '/backstop.js') . ' ' . $mode,
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
     * @param array<string, string> $env
     */
    protected function executeScript(string $scriptName, array $env, SymfonyStyle $io): int
    {
        $cmd = 'bash ' . escapeshellarg($this->joinPath(__DIR__ . '/..', $scriptName));

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
    }

    /**
     * Initialises the environment and handles login.
     * Returns the resolved INVRT_* env array on success, or an exit code int on failure.
     *
     * @return array<string, string>|int
     */
    private function boot(InvrtInput $opts, SymfonyStyle $io, bool $requiresConfig = true): array|int
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
}
