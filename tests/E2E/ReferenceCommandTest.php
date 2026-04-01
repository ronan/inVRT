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

        # Add a fake crawled urls file because we don't yet seem to auto-trigger a crawl.
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
