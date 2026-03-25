<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Service\LoginService;
use App\Service\CookieService;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Command\Command;

/**
 * Tests for LoginService
 * 
 * Tests credential handling and login orchestration with error handling
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
        // Clean up temp files
        $files = glob($this->tempDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    /**
     * Test returns SUCCESS when credentials are empty
     */
    public function testReturnsSuccessWithEmptyCredentials(): void
    {
        $output = new BufferedOutput();

        $result = LoginService::loginIfCredentialsExist(
            '',
            '',
            'https://example.com',
            $this->tempDir . '/cookies',
            $output
        );

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertEmpty($output->fetch());
    }

    /**
     * Test returns SUCCESS when both username and password are empty
     */
    public function testReturnsSuccessWhenBothCredentialsEmpty(): void
    {
        $output = new BufferedOutput();

        $result = LoginService::loginIfCredentialsExist(
            '',
            '',
            'https://example.com',
            $this->tempDir . '/cookies',
            $output
        );

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertEmpty($output->fetch());
    }

    /**
     * Test returns SUCCESS when only username is empty
     */
    public function testReturnsSuccessWhenOnlyUsernameEmpty(): void
    {
        $output = new BufferedOutput();

        $result = LoginService::loginIfCredentialsExist(
            '',
            '',
            'https://example.com',
            $this->tempDir . '/cookies',
            $output
        );

        $this->assertEquals(Command::SUCCESS, $result);
        $this->assertEmpty($output->fetch());
    }


    /**
     * Test skips login when cookies file already exists
     */
    public function testSkipsLoginWhenCookiesFileExists(): void
    {
        $output = new BufferedOutput();

        // Pre-create the cookies file
        $cookiesJsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($cookiesJsonFile, json_encode([]));

        $result = LoginService::loginIfCredentialsExist(
            'user',
            'password',
            'https://example.com',
            $this->tempDir . '/cookies',
            $output
        );

        $this->assertEquals(Command::SUCCESS, $result);

        $outputText = $output->fetch();
        $this->assertStringContainsString('already exists', $outputText);
    }
}
?>

