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
        $this->setupFixture();
        $this->fixture->deleteConfig();
        $this->executeCommand('reference');
        $this->assertCommandFailure(1);
        $this->assertOutputContains('Configuration file not found');
    }

    public function testTestHappyPathForTestingScreenshots(): void
    {
        $this->setUpFixture(true);
        $this->fixture->writeCrawledUrlsFile('local', 'anonymous', ['/', '/about.html']);

        // Run test without any prior reference — should auto-capture references then succeed
        $this->executeCommand('test', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        $this->assertOutputContains('No reference screenshots found');

        // Both reference and test bitmaps should exist
        $dataDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/desktop/bitmaps';
        $this->assertDirectoryExists($dataDir . '/reference');
        $this->assertDirectoryExists($dataDir . '/test');
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/reference')));
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/test')));

        // Run visual regression test with verbose — identical site, so no regressions
        $this->executeCommand('test');
        $this->assertCommandSuccess();

        // Output status line
        $this->assertOutputContains('🔬 Testing');
        $this->assertOutputContains($this->webserverUrl());

        // Test bitmaps created
        $testDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/desktop/bitmaps/test';
        $this->assertDirectoryExists($testDir);
        $pngs = $this->findPngs($testDir);
        $this->assertGreaterThan(0, count($pngs));
    }

    public function testTestAutoTriggersReferenceAndCrawlOnFirstRun(): void
    {
        $this->setUpFixture(true);

        // No crawled_urls.txt and no reference bitmaps yet.
        $this->executeCommand('test', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        $this->assertOutputContains('📸 No reference screenshots found — capturing references first.');
        $this->assertOutputContains('🕸️ No crawled URLs found — running crawl first.');

        $dataDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/desktop/bitmaps';
        $this->assertDirectoryExists($dataDir . '/reference');
        $this->assertDirectoryExists($dataDir . '/test');
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/reference')));
        $this->assertGreaterThan(0, count($this->findPngs($dataDir . '/test')));
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
