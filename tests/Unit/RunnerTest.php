<?php

namespace Tests\Unit;

use InVRT\Core\Configuration;
use InVRT\Core\Runner;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RunnerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/invrt-runner-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    public function testConfigurePlaywrightWritesConfigFile(): void
    {
        $configFile = $this->tempDir . '/playwright.config.ts';

        $config = new Configuration('', [
            'INVRT_PLAYWRIGHT_CONFIG_FILE' => $configFile,
        ]);

        $runner = new Runner($config, __DIR__ . '/../../src/js', new NullLogger());
        $result = $runner->configurePlaywright();

        $this->assertSame(0, $result);
        $this->assertFileExists($configFile);

        $contents = file_get_contents($configFile);
        $this->assertStringContainsString('defineConfig', $contents);
        $this->assertStringContainsString('snapshotPathTemplate', $contents);
    }

    public function testConfigurePlaywrightCreatesDirectoryIfMissing(): void
    {
        $subDir     = $this->tempDir . '/nested/dir';
        $configFile = $subDir . '/playwright.config.ts';

        $config = new Configuration('', [
            'INVRT_PLAYWRIGHT_CONFIG_FILE' => $configFile,
        ]);

        $runner = new Runner($config, __DIR__ . '/../../src/js', new NullLogger());
        $result = $runner->configurePlaywright();

        $this->assertSame(0, $result);
        $this->assertFileExists($configFile);
    }

    public function testConfigurePlaywrightFailsWhenConfigFileEmpty(): void
    {
        $config = new Configuration('', [
            'INVRT_PLAYWRIGHT_CONFIG_FILE' => '',
        ]);
        $runner = new Runner($config, __DIR__ . '/../../src/js', new NullLogger());
        $result = $runner->configurePlaywright();

        $this->assertSame(1, $result);
    }

    // -------------------------------------------------------------------------

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
