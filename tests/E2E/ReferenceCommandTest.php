<?php

namespace Tests\E2E;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * E2E tests for ReferenceCommand
 *
 * Starts a PHP built-in webserver serving tests/fixtures/website/, crawls two pages,
 * and asserts that reference screenshots are created with non-zero file sizes.
 */
class ReferenceCommandTest extends WebCommandTestCase
{
    public function testRequiresConfig(): void
    {
        $this->setupFixture();
        $this->fixture->deleteConfig();
        $this->executeCommand('reference');
        $this->assertCommandFailure(1);
        $this->assertOutputContains('Configuration file not found');
    }

    public function testReferenceCommandCapturesScreenshots(): void
    {
        $this->setupFixture();
        $this->fixture->writeCrawledUrlsFile('local', 'anonymous', ['/', '/about.html']);

        $this->executeCommand('reference', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        // Output status line
        $this->assertOutputContains('📸 Capturing references');
        $this->assertOutputContains($this->webserverUrl());

        // PNGs created
        $refDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/bitmaps/reference';
        $this->assertDirectoryExists($refDir);
        $pngs = $this->findPngs($refDir);
        $this->assertGreaterThanOrEqual(2, count($pngs));
        foreach ($pngs as $png) {
            $this->assertGreaterThan(0, filesize($png), "PNG $png should not be empty");
        }
    }

    public function testReferenceAutoTriggersCrawlWhenNoCrawlFileExists(): void
    {
        $this->setupFixture();

        // No crawled_urls.txt — reference should auto-trigger crawl first
        $this->executeCommand('reference', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();

        // Crawl auto-trigger message
        $this->assertOutputContains('🕸️ No crawled URLs found — running crawl first.');

        // Reference proceeded after crawl
        $this->assertOutputContains('📸 Capturing references');

        // PNGs created
        $refDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/bitmaps/reference';
        $this->assertDirectoryExists($refDir);
        $pngs = $this->findPngs($refDir);
        $this->assertGreaterThanOrEqual(1, count($pngs));
        foreach ($pngs as $png) {
            $this->assertGreaterThan(0, filesize($png), "PNG $png should not be empty");
        }
    }

    public function testReferenceCommandShowsDebugOutputAtVvv(): void
    {
        $this->setupFixture();
        $this->fixture->writeCrawledUrlsFile('local', 'anonymous', ['/']);

        $this->executeCommand('reference', [], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);
        $this->assertCommandSuccess();

        $this->assertOutputContains('[debug] Bootstrapping command');
        $this->assertOutputContains('[debug] Running BackstopJS command');
        $this->assertOutputContains('Using Playwright engine scripts from:');
        $this->assertOutputContains('[debug] BackstopJS exit code: 0');
    }

    public function testReferenceFailsWhenCrawlFileIsEmpty(): void
    {
        $this->setupFixture();
        $this->fixture->writeCrawledUrlsFile('local', 'anonymous', []);

        $this->executeCommand('reference', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandFailure();
        $this->assertOutputContains('No crawled URLs are available. Crawl has run but found no usable URLs.');
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
