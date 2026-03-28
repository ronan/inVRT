<?php

namespace Tests\Unit;

use App\Service\LoginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Tests for LoginService
 */
class LoginServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/login-service-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    public function testSkipsLoginWhenCookiesFileExists(): void
    {
        $output = new BufferedOutput(BufferedOutput::VERBOSITY_VERBOSE);
        file_put_contents($this->tempDir . '/cookies.json', json_encode([]));

        $result = LoginService::loginIfCredentialsExist(
            'user',
            'password',
            'https://example.com',
            $this->tempDir . '/cookies',
            $output,
        );

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertStringContainsString('already exists', $output->fetch());
    }
}


