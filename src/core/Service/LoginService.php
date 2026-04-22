<?php

namespace InVRT\Core\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

class LoginService
{
    private const EXIT_SUCCESS = 0;
    private const EXIT_FAILURE = 1;

    /**
     * Handle login if credentials exist.
     *
     * @param string          $appDir   Absolute path to directory containing playwright-login.js.
     * @return int 0 on success or no credentials; 1 on failure.
     */
    public static function loginIfCredentialsExist(
        string $username,
        string $password,
        string $url,
        string $cookiesFile,
        string $appDir,
        LoggerInterface $logger,
    ): int {
        try {
            if (empty($username) && empty($password)) {
                $logger->debug('No credentials provided; skipping login.');
                return self::EXIT_SUCCESS;
            }

            if (empty($url)) {
                $logger->error('❌ Profile has credentials but no URL configured. Cannot login.');
                return self::EXIT_FAILURE;
            }

            $loginUrl = getenv('INVRT_LOGIN_URL') ?: $url . '/user/login';

            $logger->notice('🔐 Logging in with provided credentials...');
            $logger->debug("Login URL: $loginUrl");
            $logger->debug("Cookies output file: $cookiesFile.json");

            $script = rtrim($appDir, '/') . '/playwright-login.js';

            if (!file_exists($script)) {
                $logger->error("❌ Playwright login script not found at $script");
                return self::EXIT_FAILURE;
            }

            $env = [
                'INVRT_LOGIN_URL'    => $loginUrl,
                'INVRT_USERNAME'     => $username,
                'INVRT_PASSWORD'     => $password,
                'INVRT_COOKIES_FILE' => $cookiesFile,
            ];

            $cmd = 'node ' . escapeshellarg($script);
            $logger->debug("Login command: $cmd");

            $process = Process::fromShellCommandline($cmd, null, $env);
            $process->setTimeout(null);
            $parser = new NodeOutputParser($logger);
            $process->run(function (mixed $type, mixed $buffer) use ($parser): void {
                $parser->write($buffer);
            });
            $parser->flush();
            $exitCode = $process->getExitCode() ?? 0;
            $logger->debug("Login command exit code: $exitCode");

            if ($exitCode !== 0) {
                $logger->error("❌ Playwright login failed with exit code $exitCode");
                return self::EXIT_FAILURE;
            }

            $logger->notice('✅ Login successful!');

            CookieService::convertToNetscapeFormat("$cookiesFile.json");

            return self::EXIT_SUCCESS;
        } catch (\Exception $error) {
            $logger->error('❌ Login failed: ' . $error->getMessage());
            return self::EXIT_FAILURE;
        }
    }
}
