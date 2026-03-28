<?php

namespace Tests\Unit;

use App\Service\CookieService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CookieService
 *
 * Tests cookie conversion to Netscape format for wget/curl compatibility
 */
class CookieServiceTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/cookie-service-test-' . uniqid();
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
     * Test conversion with valid cookies JSON
     */
    public function testConvertValidCookies(): void
    {
        $cookies = [
            [
                'name' => 'session_id',
                'value' => 'abc123xyz789',
                'domain' => '.example.com',
                'path' => '/',
                'secure' => true,
                'expires' => 1735689600,
            ],
            [
                'name' => 'user_id',
                'value' => '12345',
                'domain' => '.example.com',
                'path' => '/',
                'secure' => false,
                'expires' => 1735689600,
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        // Capture output
        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_end_clean();

        // Verify output file was created
        $txtFile = $this->tempDir . '/cookies.txt';
        $this->assertFileExists($txtFile);

        // Verify Netscape format
        $content = file_get_contents($txtFile);
        $this->assertStringContainsString('# Netscape HTTP Cookie File', $content);
        $this->assertStringContainsString('.example.com', $content);
        $this->assertStringContainsString('session_id', $content);
        $this->assertStringContainsString('abc123xyz789', $content);
    }

    /**
     * Test conversion with minimal cookie data
     */
    public function testConvertMinimalCookieData(): void
    {
        $cookies = [
            [
                'name' => 'test_cookie',
                'value' => 'test_value',
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $this->assertFileExists($txtFile);

        $content = file_get_contents($txtFile);
        // Should use defaults for missing fields
        $this->assertStringContainsString('.localhost', $content);
        $this->assertStringContainsString('test_cookie', $content);
        $this->assertStringContainsString('test_value', $content);
    }

    /**
     * Test handles missing JSON file gracefully
     */
    public function testHandlesMissingJsonFile(): void
    {
        $jsonFile = $this->tempDir . '/nonexistent.json';

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        $output = ob_get_clean();

        // Should output info message
        $this->assertStringContainsString('Cookies file not found', $output);

        // Output txt file should NOT be created
        $txtFile = $this->tempDir . '/nonexistent.txt';
        $this->assertFileDoesNotExist($txtFile);
    }

    /**
     * Test secure flag handling
     */
    public function testSecureFlagHandling(): void
    {
        $cookies = [
            [
                'name' => 'secure_cookie',
                'value' => 'secure_value',
                'domain' => '.example.com',
                'path' => '/',
                'secure' => true,
                'expires' => 1735689600,
            ],
            [
                'name' => 'insecure_cookie',
                'value' => 'insecure_value',
                'domain' => '.example.com',
                'path' => '/',
                'secure' => false,
                'expires' => 1735689600,
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $content = file_get_contents($txtFile);
        $lines = explode("\n", $content);

        // Find cookie lines (skip header)
        $cookieLines = array_filter($lines, fn($line) => !empty($line) && strpos($line, '#') === false);

        $this->assertCount(2, $cookieLines);
    }

    /**
     * Test domain default value
     */
    public function testDomainDefaultValue(): void
    {
        $cookies = [
            [
                'name' => 'test',
                'value' => 'value',
                // No domain specified
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $content = file_get_contents($txtFile);

        // Should use .localhost as default
        $this->assertStringContainsString('.localhost', $content);
    }

    /**
     * Test path default value
     */
    public function testPathDefaultValue(): void
    {
        $cookies = [
            [
                'name' => 'test',
                'value' => 'value',
                'domain' => '.example.com',
                // No path specified
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $content = file_get_contents($txtFile);

        // Should use / as default path
        $this->assertStringContainsString("\t/\t", $content);
    }

    /**
     * Test empty cookies array
     */
    public function testEmptyCookiesArray(): void
    {
        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode([]));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $this->assertFileExists($txtFile);

        $content = file_get_contents($txtFile);
        // Should have header but no cookie data
        $this->assertStringContainsString('# Netscape HTTP Cookie File', $content);
        $lines = explode("\n", $content);
        $cookieLines = array_filter($lines, fn($line) => !empty($line) && strpos($line, '#') === false);
        $this->assertEmpty($cookieLines);
    }

    /**
     * Test special characters in cookie values
     */
    public function testSpecialCharactersInCookieValues(): void
    {
        $cookies = [
            [
                'name' => 'special_cookie',
                'value' => 'value-with_special.chars=123',
                'domain' => '.example.com',
                'path' => '/path/with/slashes',
            ],
        ];

        $jsonFile = $this->tempDir . '/cookies.json';
        file_put_contents($jsonFile, json_encode($cookies));

        ob_start();
        CookieService::convertToNetscapeFormat($jsonFile);
        ob_get_clean();

        $txtFile = $this->tempDir . '/cookies.txt';
        $content = file_get_contents($txtFile);

        $this->assertStringContainsString('special_cookie', $content);
        $this->assertStringContainsString('value-with_special.chars=123', $content);
        $this->assertStringContainsString('/path/with/slashes', $content);
    }
}
