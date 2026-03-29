<?php

namespace App\Commands;

use App\Input\InvrtInput;
use App\Service\EnvironmentService;
use App\Service\LoginService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Process\Process;

abstract class BaseCommand
{
    public function __construct(protected readonly EnvironmentService $env) {}

    /**
     * Initialise environment + login.
     * Returns the resolved INVRT_* env array on success, or an exit code int on failure.
     *
     * @return array<string, string>|int
     */
    protected function boot(InvrtInput $opts, SymfonyStyle $io, bool $requiresConfig = true): array|int
    {
        $env = $this->env->initialize($opts->profile, $opts->device, $opts->environment, $io, $requiresConfig);

        $loginResult = LoginService::loginIfCredentialsExist(
            $env['INVRT_USERNAME'] ?? '',
            $env['INVRT_PASSWORD'] ?? '',
            $env['INVRT_URL'] ?? '',
            $env['INVRT_COOKIES_FILE'] ?? '',
            $io,
        );

        return $loginResult !== Command::SUCCESS ? $loginResult : $env;
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
        $cmd = 'bash ' . escapeshellarg(Path::join(__DIR__ . '/..', $scriptName));

        $process = Process::fromShellCommandline($cmd, null, $env);
        $process->setTimeout(null);
        $process->run(fn($type, $buffer) => $io->write($buffer));

        return $process->getExitCode() ?? Command::SUCCESS;
    }
}
