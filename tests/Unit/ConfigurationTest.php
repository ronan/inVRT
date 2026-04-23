<?php

namespace Tests\Unit;

use InVRT\Core\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/invrt-config-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            is_file($file) && unlink($file);
        }
        is_dir($this->tempDir) && rmdir($this->tempDir);
    }

    public function testNoWarningsForValidConfig(): void
    {
        $path = $this->tempDir . '/config.yaml';
        file_put_contents($path, "project:\n  url: https://example.test\n");

        $config = new Configuration($path);

        $this->assertSame([], $config->getWarnings());
        $this->assertSame('https://example.test', $config->get('INVRT_URL'));
    }

    public function testWarningAndContinuesForUnknownKeys(): void
    {
        $path = $this->tempDir . '/config.yaml';
        file_put_contents($path, "project:\n  url: https://example.test\n  unknown_key: bad_value\n");

        $config = new Configuration($path, ['INVRT_CWD' => $this->tempDir]);

        $warnings = $config->getWarnings();
        $this->assertNotEmpty($warnings);
        $this->assertStringContainsStringIgnoringCase('unexpected', $warnings[0]);
        // URL still resolved from raw data
        $this->assertSame('https://example.test', $config->get('INVRT_URL'));
    }
}
