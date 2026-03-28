<?php

namespace Tests\E2E;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * E2E tests for TestCommand
 *
 * Seeds reference screenshots via the reference command, then runs the test command
 * and asserts that comparison screenshots are created and the command succeeds (no regressions).
 */
class TestCommandTest extends WebCommandTestCase
{
    public function testRequiresConfig(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Configuration file not found/');
        $this->executeCommand('test');
    }

    public function testTestCommandRunsComparison(): void
    {
        $this->setupFixture();

        // Seed reference screenshots
        $this->executeCommand('reference');
        $this->assertCommandSuccess();

        // Run visual regression test — identical site, so no regressions
        $this->executeCommand('test');
        $this->assertCommandSuccess();

        $testDir = $this->fixture->getInvrtDir() . '/data/anonymous/local/bitmaps/test';
        $this->assertDirectoryExists($testDir, 'Test bitmaps directory should exist');

        $pngs = $this->findPngs($testDir);
        $this->assertGreaterThan(0, count($pngs), 'Expected test PNGs to be created');
    }

    public function testTestCommandOutputContainsStatusLine(): void
    {
        $this->setupFixture();
        $this->executeCommand('reference'); // seed references
        $this->executeCommand('test', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();
        $this->assertOutputContains('🔬 Testing');
        $this->assertOutputContains($this->webserverUrl());
    }

    /** @return string[] */
    private function findPngs(string $dir): array
    {
        $pngs = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($it as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'png') {
                $pngs[] = $file->getPathname();
            }
        }
        return $pngs;
    }
}
