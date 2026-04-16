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

    public function testCrawlFailsWhenNoUsableUrlsAreFound(): void
    {
        $this->setUpFixture(true);
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => 'http://127.0.0.1:1'],
            ],
        ]);

        $this->executeCommand('crawl', [], ['verbosity' => OutputInterface::VERBOSITY_DEBUG]);

        $this->assertCommandFailure();
        $this->assertOutputContains('No usable URLs were found during crawl.');
        $this->assertOutputContains('Last 5 lines of crawl log:');

        $urlsFile = $this->fixture->getInvrtDir() . '/data/local/anonymous/crawled_urls.txt';
        $this->assertFileExists($urlsFile);
        $this->assertSame('', file_get_contents($urlsFile));
    }

    public function testCrawlAutoInitializesWhenConfigIsMissing(): void
    {
        $this->fixture->deleteInvrtDirectory();
        $this->executeCommandWithInputs('crawl', [$this->webserverUrl()], [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);

        $this->assertCommandSuccess();
        $this->assertOutputContains('No configuration file found. Initializing inVRT first.');
        $this->assertOutputContains('What URL should inVRT use?');

        $urlsFile = $this->fixture->getInvrtDir() . '/data/local/anonymous/crawled_urls.txt';
        $this->assertFileExists($urlsFile);
        $this->assertGreaterThan(0, count(array_filter(explode("\n", (string) file_get_contents($urlsFile)))));
        $this->assertConfigValue('environments.local.url', $this->webserverUrl());
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
