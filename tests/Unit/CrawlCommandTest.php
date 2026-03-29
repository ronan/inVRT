<?php

namespace Tests\Unit;

use App\Commands\CrawlCommand;
use App\Service\EnvironmentService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CrawlCommand::parseUrlsFromLog()
 */
class CrawlCommandTest extends TestCase
{
    private string $tempDir;
    private \ReflectionMethod $method;
    private CrawlCommand $command;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/crawl-command-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $this->command = new CrawlCommand(new EnvironmentService());
        $this->method = new \ReflectionMethod(CrawlCommand::class, 'parseUrlsFromLog');
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    private function parse(string $logFile, string $baseUrl = 'https://example.com'): array
    {
        $outputFile = "$this->tempDir/urls.txt";
        $count = $this->method->invoke($this->command, $logFile, $baseUrl, $outputFile);

        $lines = file_exists($outputFile)
            ? array_filter(explode("\n", trim(file_get_contents($outputFile) ?: '')))
            : [];

        return ['count' => $count, 'urls' => array_values($lines)];
    }

    public function testParsesAllUrlsFromFixture(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        $this->assertSame(95, $result['count']);
        $this->assertCount(95, $result['urls']);
    }

    public function testOutputIsSortedAndUnique(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        $sorted = $result['urls'];
        sort($sorted);

        $this->assertSame($sorted, $result['urls'], 'URLs should be sorted');
        $this->assertSame(array_unique($result['urls']), $result['urls'], 'URLs should be deduplicated');
    }

    public function testOutputContainsExpectedPaths(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        // Spot-check a few known paths from the fixture
        $this->assertContains('/', $result['urls']);
        $this->assertContains('/about', $result['urls']);
        $this->assertContains('/blog', $result['urls']);
        $this->assertContains('/user/login', $result['urls']);
    }

    public function testDeduplicatesUrls(): void
    {
        $log = "$this->tempDir/dup.log";
        file_put_contents($log, implode("\n", [
            '2026-01-01 00:00:01 URL:https://example.com/page [100] -> "clone/page" [1]',
            '2026-01-01 00:00:02 URL:https://example.com/page [100] -> "clone/page" [1]',
            '2026-01-01 00:00:03 URL:https://example.com/other [200] -> "clone/other" [1]',
        ]));

        $result = $this->parse($log);

        $this->assertSame(2, $result['count']);
        $this->assertSame(['/other', '/page'], $result['urls']);
    }

    public function testIgnoresLinesFromOtherHosts(): void
    {
        $log = "$this->tempDir/mixed.log";
        file_put_contents($log, implode("\n", [
            '2026-01-01 00:00:01 URL:https://example.com/page [100] -> "clone/page" [1]',
            '2026-01-01 00:00:02 URL:https://other.com/page [100] -> "clone/page" [1]',
        ]));

        $result = $this->parse($log);

        $this->assertSame(1, $result['count']);
        $this->assertSame(['/page'], $result['urls']);
    }

    public function testReturnsZeroForMissingLogFile(): void
    {
        $result = $this->parse("$this->tempDir/nonexistent.log");

        $this->assertSame(0, $result['count']);
        $this->assertSame([], $result['urls']);
    }

    public function testWritesEmptyFileWhenNoUrlsFound(): void
    {
        $log = "$this->tempDir/empty.log";
        file_put_contents($log, "no matching lines here\n");
        $outputFile = "$this->tempDir/urls.txt";

        $this->method->invoke($this->command, $log, 'https://example.com', $outputFile);

        $this->assertSame('', file_get_contents($outputFile));
    }
}
