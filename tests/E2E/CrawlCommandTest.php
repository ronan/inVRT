<?php

namespace Tests\E2E;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * E2E tests for CrawlCommand
 *
 * Starts a PHP built-in webserver serving tests/fixtures/website/ (5 pages),
 * runs a real wget crawl, and asserts crawled_urls.txt is correctly populated.
 */
class CrawlCommandTest extends WebCommandTestCase
{

    public function testCrawlHappyPath(): void
    {
        $this->setupCrawlFixture();
        $this->executeCommand('crawl', [], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);
        $this->assertCommandSuccess();

        // Output status line
        $this->assertOutputContains('🕸️ Crawling');
        $this->assertOutputContains($this->webserverUrl());

    }

    public function testCrawlHappyPathExtended(): void
    {
        $this->setupCrawlFixture();
        $this->executeCommand('crawl', [], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);
        $this->assertCommandSuccess();

        // URLs file populated with discovered pages
        $urlsFile = $this->fixture->getInvrtDir() . '/data/local/anonymous/crawled_urls.txt';
        $this->assertFileExists($urlsFile);
        $urls = array_filter(explode("\n", file_get_contents($urlsFile)));
        $this->assertGreaterThanOrEqual(5, count($urls));
        foreach (['/about.html', '/services.html', '/contact.html', '/blog.html'] as $path) {
            $this->assertContains($path, $urls, "Expected $path to be discovered");
        }

        // Log file created
        $logFile = $this->fixture->getInvrtDir() . '/data/local/anonymous/logs/crawl.log';
        $this->assertFileExists($logFile);
    }

    public function testCrawlWithEnvironmentOption(): void
    {
        $this->setupCrawlFixture();
        $this->executeCommand('crawl', ['--environment' => 'local']);
        $this->assertCommandSuccess();
    }

    /** Write config pointing at the test webserver — no pre-seeded URL list needed. */
    private function setupCrawlFixture(): void
    {
        $this->setUpFixture(true);
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => $this->webserverUrl()],
            ],
        ]);
    }
}

