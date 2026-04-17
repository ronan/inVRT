<?php

namespace Tests\E2E;

class InfoCommandTest extends CommandTestCase
{
    public function testInfoShowsProjectSummary(): void
    {
        $this->fixture->writeConfig([
            'name' => 'My Test Project',
            'environments' => [
                'local'   => ['url' => 'https://local.example.com'],
                'staging' => ['url' => 'https://staging.example.com'],
            ],
            'profiles' => [
                'anonymous' => [],
                'admin'     => ['username' => 'admin', 'password' => 'secret'],
            ],
            'devices' => [
                'desktop' => ['viewport_width' => 1024, 'viewport_height' => 768],
                'mobile'  => ['viewport_width' => 375,  'viewport_height' => 667],
            ],
        ]);

        $this->executeCommand('info');

        $this->assertCommandSuccess();
        $this->assertOutputContains('My Test Project');
        $this->assertOutputContains('local');
        $this->assertOutputContains('anonymous');
        $this->assertOutputContains('desktop');
        $this->assertOutputContains('staging');
        $this->assertOutputContains('admin');
        $this->assertOutputContains('mobile');
    }

    public function testInfoShowsZeroCrawledPagesWhenCrawlNotRun(): void
    {
        $this->fixture->writeMinimalConfig();

        $this->executeCommand('info');

        $this->assertCommandSuccess();
        $this->assertOutputContains('Crawled pages');
        $this->assertOutputContains('0');
    }

    public function testInfoShowsCrawledPageCountWhenCrawlFileExists(): void
    {
        $this->fixture->writeConfig([
            'name' => 'Count Test',
            'environments' => ['local' => ['url' => 'https://example.com']],
            'profiles'     => ['anonymous' => []],
            'devices'      => ['desktop' => []],
        ]);

        // Write a fake crawled_urls.txt with 3 paths
        $crawlDir = $this->fixture->getInvrtDir() . '/data/local/anonymous';
        @mkdir($crawlDir, 0755, true);
        file_put_contents($crawlDir . '/crawled_urls.txt', "/\n/about\n/contact\n");

        $this->executeCommand('info', ['--environment' => 'local', '--profile' => 'anonymous']);

        $this->assertCommandSuccess();
        $this->assertOutputContains('3');
    }
}
