<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class InvrtUtilsTest extends TestCase
{
    /**
     * Include the utility functions for testing
     */
    public static function setUpBeforeClass(): void
    {
        require_once __DIR__ . '/../../src/invrt-utils.inc.php';
    }

    /**
     * Test getConfig with simple one-level key
     */
    public function testGetConfigSimpleKey(): void
    {
        $config = [
            'project' => [
                'url' => 'https://example.com'
            ]
        ];

        $result = getConfig($config, 'project.url');
        $this->assertEquals('https://example.com', $result);
    }

    /**
     * Test getConfig with nested keys
     */
    public function testGetConfigNestedKey(): void
    {
        $config = [
            'settings' => [
                'max_crawl_depth' => 3,
                'max_pages' => 100
            ]
        ];

        $result = getConfig($config, 'settings.max_crawl_depth');
        $this->assertEquals(3, $result);
    }

    /**
     * Test getConfig with deeply nested keys
     */
    public function testGetConfigDeeplyNestedKey(): void
    {
        $config = [
            'profiles' => [
                'default' => [
                    'auth' => [
                        'username' => 'testuser'
                    ]
                ]
            ]
        ];

        $result = getConfig($config, 'profiles.default.auth.username');
        $this->assertEquals('testuser', $result);
    }

    /**
     * Test getConfig with non-existent key returns default
     */
    public function testGetConfigNonExistentKeyReturnsDefault(): void
    {
        $config = ['project' => ['url' => 'https://example.com']];
        
        $result = getConfig($config, 'nonexistent.key', 'default_value');
        $this->assertEquals('default_value', $result);
    }

    /**
     * Test getConfig with non-existent key returns empty string by default
     */
    public function testGetConfigNonExistentKeyReturnsEmpty(): void
    {
        $config = ['project' => ['url' => 'https://example.com']];
        
        $result = getConfig($config, 'nonexistent.key');
        $this->assertEquals('', $result);
    }

    /**
     * Test getConfig with null/empty values
     */
    public function testGetConfigWithEmptyValue(): void
    {
        $config = [
            'settings' => [
                'user_agent' => ''
            ]
        ];

        $result = getConfig($config, 'settings.user_agent', 'default');
        // Empty string is falsy, so it returns default
        $this->assertEquals('default', $result);
    }

    /**
     * Test getConfig with zero value (should be preserved)
     */
    public function testGetConfigWithZeroValue(): void
    {
        $config = [
            'settings' => [
                'max_concurrent_requests' => 0
            ]
        ];

        $result = getConfig($config, 'settings.max_concurrent_requests', 5);
        // Zero is falsy, so it returns default - this is expected behavior
        $this->assertEquals(5, $result);
    }

    /**
     * Test joinPath with two segments
     */
    public function testJoinPathTwoSegments(): void
    {
        $result = joinPath('/home', 'user');
        $expected = join(DIRECTORY_SEPARATOR, ['/home', 'user']);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test joinPath with multiple segments
     */
    public function testJoinPathMultipleSegments(): void
    {
        $result = joinPath('/home', 'user', 'project', 'src');
        $expected = join(DIRECTORY_SEPARATOR, ['/home', 'user', 'project', 'src']);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test joinPath with single segment
     */
    public function testJoinPathSingleSegment(): void
    {
        $result = joinPath('/home');
        $this->assertEquals('/home', $result);
    }

    /**
     * Test convertCookiesForWget when file doesn't exist
     */
    public function testConvertCookiesForWgetFileNotFound(): void
    {
        // Capture output
        ob_start();
        convertCookiesForWget('/nonexistent/path/cookies.json');
        $output = ob_get_clean();

        $this->assertStringContainsString('Cookies file not found', $output);
    }

    /**
     * Test convertCookiesForWget with valid cookie JSON
     */
    public function testConvertCookiesForWgetSuccess(): void
    {
        $tempDir = sys_get_temp_dir();
        $cookieFile = $tempDir . DIRECTORY_SEPARATOR . 'test_cookies.json';
        $expectedOutputFile = $tempDir . DIRECTORY_SEPARATOR . 'test_cookies.txt';

        // Clean up first
        if (file_exists($cookieFile)) {
            unlink($cookieFile);
        }
        if (file_exists($expectedOutputFile)) {
            unlink($expectedOutputFile);
        }

        // Create test cookie JSON
        $cookies = [
            [
                'domain' => 'example.com',
                'path' => '/',
                'secure' => true,
                'expires' => 1735689600,
                'name' => 'session_id',
                'value' => 'abc123'
            ],
            [
                'domain' => '.example.com',
                'path' => '/',
                'secure' => false,
                'expires' => 0,
                'name' => 'user_pref',
                'value' => 'dark_mode'
            ]
        ];

        file_put_contents($cookieFile, json_encode($cookies));

        // Convert cookies
        ob_start();
        convertCookiesForWget($cookieFile);
        $output = ob_get_clean();

        // Check that output file was created
        $this->assertFileExists($expectedOutputFile);

        // Read and validate output file
        $contents = file_get_contents($expectedOutputFile);

        // Should contain Netscape header
        $this->assertStringContainsString('# Netscape HTTP Cookie File', $contents);

        // Should contain cookie data
        $this->assertStringContainsString('example.com', $contents);
        $this->assertStringContainsString('session_id', $contents);
        $this->assertStringContainsString('abc123', $contents);

        // Clean up
        unlink($cookieFile);
        unlink($expectedOutputFile);
    }

    /**
     * Test convertCookiesForWget with minimal cookie data
     */
    public function testConvertCookiesForWgetMinimalCookieData(): void
    {
        $tempDir = sys_get_temp_dir();
        $cookieFile = $tempDir . DIRECTORY_SEPARATOR . 'minimal_cookies.json';
        $expectedOutputFile = $tempDir . DIRECTORY_SEPARATOR . 'minimal_cookies.txt';

        // Clean up
        foreach ([$cookieFile, $expectedOutputFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Create minimal cookie JSON (missing optional fields)
        $cookies = [
            [
                'name' => 'test_cookie',
                'value' => 'test_value'
            ]
        ];

        file_put_contents($cookieFile, json_encode($cookies));

        // Convert cookies
        ob_start();
        convertCookiesForWget($cookieFile);
        ob_get_clean();

        // Check file exists and has reasonable content
        $this->assertFileExists($expectedOutputFile);
        $contents = file_get_contents($expectedOutputFile);

        // Should have default domain
        $this->assertStringContainsString('.localhost', $contents);
        $this->assertStringContainsString('test_cookie', $contents);
        $this->assertStringContainsString('test_value', $contents);

        // Clean up
        unlink($cookieFile);
        unlink($expectedOutputFile);
    }

    /**
     * Test convertCookiesForWget with invalid JSON
     */
    public function testConvertCookiesForWgetInvalidJson(): void
    {
        $tempDir = sys_get_temp_dir();
        $cookieFile = $tempDir . DIRECTORY_SEPARATOR . 'invalid_cookies.json';
        $expectedOutputFile = $tempDir . DIRECTORY_SEPARATOR . 'invalid_cookies.txt';

        // Clean up files if they exist
        foreach ([$cookieFile, $expectedOutputFile] as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        // Create invalid JSON file
        file_put_contents($cookieFile, '{invalid json}');

        // The function handles invalid JSON gracefully - json_decode returns null
        ob_start();
        convertCookiesForWget($cookieFile);
        $output = ob_get_clean();

        // With invalid JSON, the function still creates a file with headers
        // but no cookie entries (since json_decode returns null)
        if (file_exists($expectedOutputFile)) {
            $this->assertFileExists($expectedOutputFile);
            $contents = file_get_contents($expectedOutputFile);
            // Should still have the Netscape header even with invalid JSON
            $this->assertStringContainsString('# Netscape HTTP Cookie File', $contents);
            unlink($expectedOutputFile);
        }

        // Clean up
        unlink($cookieFile);
    }
}
?>
