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
    public function testRequiresConfig(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Configuration file not found/');
        $this->executeCommand('crawl');
    }

    public function testCrawlDiscoversAllPages(): void
    {
        $this->setupCrawlFixture();

        $this->executeCommand('crawl');
        $this->assertCommandSuccess();

        $urlsFile = $this->fixture->getInvrtDir() . '/data/anonymous/local/crawled_urls.txt';
        $this->assertFileExists($urlsFile, 'crawled_urls.txt should be created after crawl');

        $urls = array_filter(explode("\n", file_get_contents($urlsFile)));
        $this->assertGreaterThanOrEqual(5, count($urls), 'Expected at least 5 crawled paths');

        foreach (['/about.html', '/services.html', '/contact.html', '/blog.html'] as $path) {
            $this->assertContains($path, $urls, "Expected $path to be discovered");
        }
    }

    public function testCrawlOutputContainsStatusLine(): void
    {
        $this->setupCrawlFixture();

        $this->executeCommand('crawl', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $this->assertCommandSuccess();
        $this->assertOutputContains('🕸️ Crawling');
        $this->assertOutputContains($this->webserverUrl());
    }

    public function testCrawlWithEnvironmentOption(): void
    {
        $this->setupCrawlFixture();

        $this->executeCommand('crawl', ['--environment' => 'local']);
        $this->assertCommandSuccess();

        $urlsFile = $this->fixture->getInvrtDir() . '/data/anonymous/local/crawled_urls.txt';
        $this->assertFileExists($urlsFile);
    }

    public function testCrawlCreatesLogFile(): void
    {
        $this->setupCrawlFixture();

        $this->executeCommand('crawl');
        $this->assertCommandSuccess();

        $logFile = $this->fixture->getInvrtDir() . '/data/anonymous/local/logs/crawl.log';
        $this->assertFileExists($logFile, 'crawl.log should be created by wget');
    }

    /** Write config pointing at the test webserver — no pre-seeded URL list needed. */
    private function setupCrawlFixture(): void
    {
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => $this->webserverUrl()],
            ],
        ]);
    }
}

