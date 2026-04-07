<?php

namespace App\Service;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

class LoginService
{
    /**
     * Handle login if credentials exist
     *
     * @return int Command::SUCCESS on success or credentials not provided, Command::FAILURE on error
     */
    public static function loginIfCredentialsExist(
        string $username,
        string $password,
        string $url,
        string $cookiesFile,
        OutputInterface $output,
    ): int {
        try {
            if (empty($username) && empty($password)) {
                $output->writeln('[debug] No credentials provided; skipping login.', OutputInterface::VERBOSITY_DEBUG);
                return Command::SUCCESS;
            }

            // Check if URL is configured
            if (empty($url)) {
                $output->writeln("<error>❌ Profile has credentials but no URL configured. Cannot login.</error>", OutputInterface::VERBOSITY_QUIET);
                return Command::FAILURE;
            }

            // Check if cookies file already exists
            // if (file_exists("$cookiesFile.json")) {
            //     $output->writeln("<comment>⚠️ Cookies file already exists at $cookiesFile.json. Skipping login to avoid overwriting existing cookies.</comment>", OutputInterface::VERBOSITY_VERBOSE);
            //     return Command::SUCCESS;
            // }

            // Set default login URL if not provided
            $loginUrl = getenv('INVRT_LOGIN_URL');
            if (!$loginUrl) {
                $loginUrl = $url . "/user/login";
            }

            $output->writeln("🔐 Logging in with provided credentials...", OutputInterface::VERBOSITY_NORMAL);
            $output->writeln("[debug] Login URL: $loginUrl", OutputInterface::VERBOSITY_DEBUG);
            $output->writeln("[debug] Cookies output file: $cookiesFile.json", OutputInterface::VERBOSITY_DEBUG);

            // Execute Playwright login script
            $script = dirname(__DIR__) . "/playwright-login.js";

            if (!file_exists($script)) {
                $output->writeln("<error>❌ Playwright login script not found at $script</error>", OutputInterface::VERBOSITY_QUIET);
                return Command::FAILURE;
            }

            $env = [
                'INVRT_LOGIN_URL' => $loginUrl,
                'INVRT_USERNAME' => $username,
                'INVRT_PASSWORD' => $password,
                'INVRT_COOKIES_FILE' => $cookiesFile,
            ];

            $cmd = 'node ' . escapeshellarg($script);
            $output->writeln("[debug] Login command: $cmd", OutputInterface::VERBOSITY_DEBUG);

            $process = Process::fromShellCommandline($cmd, null, $env);
            $process->setTimeout(null);
            $process->run(fn($type, $buffer) => $output->write($buffer));
            $exitCode = $process->getExitCode() ?? 0;
            $output->writeln("[debug] Login command exit code: $exitCode", OutputInterface::VERBOSITY_DEBUG);

            if ($exitCode !== 0) {
                $output->writeln("<error>❌ Playwright login failed with exit code $exitCode</error>", OutputInterface::VERBOSITY_QUIET);
                return Command::FAILURE;
            }

            $output->writeln("<info>✅ Login successful!</info>", OutputInterface::VERBOSITY_NORMAL);

            // Convert cookies to wget format
            CookieService::convertToNetscapeFormat("$cookiesFile.json");

            return Command::SUCCESS;
        } catch (\Exception $error) {
            $output->writeln("<error>❌ Login failed: " . $error->getMessage() . "</error>", OutputInterface::VERBOSITY_QUIET);
            return Command::FAILURE;
        }
    }
}
