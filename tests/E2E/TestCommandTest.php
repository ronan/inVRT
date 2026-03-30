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
        $this->expectExceptionMessageMatches('/Could not find a config.yml file/');
        $this->executeCommand('test');
    }

    public function testTestHappyPathForTestingScreenshots(): void
    {
        $this->setupFixture(true);

        // Run test without any prior reference — should auto-capture references then succeed
        $this->executeCommand('test', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        $this->assertOutputContains('No reference screenshots found');

        // Both reference and test bitmaps should exist
        $dataDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/bitmaps';
        $this->assertDirectoryExists($dataDir . '/reference');
        $this->assertDirectoryExists($dataDir . '/test');
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/reference')));
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/test')));

        // Run visual regression test with verbose — identical site, so no regressions
        $this->executeCommand('test', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        // Output status line
        $this->assertOutputContains('🔬 Testing');
        $this->assertOutputContains($this->webserverUrl());

        // Test bitmaps created
        $testDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/bitmaps/test';
        $this->assertDirectoryExists($testDir);
        $pngs = $this->findPngs($testDir);
        $this->assertGreaterThan(0, count($pngs));
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
