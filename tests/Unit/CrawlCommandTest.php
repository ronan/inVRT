<?php

namespace Tests\Unit;

use InVRT\Core\Runner;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Runner::parseUrlsFromLog()
 */
class CrawlCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $class = (new \ReflectionClass($this))->getShortName();
        $this->tempDir = dirname(__DIR__, 2) . '/scratch/tests/' . $class . '/' . $this->name();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    private function parse(string $logFile, string $baseUrl = 'https://example.com'): array
    {
        return Runner::parseUrlsFromLog($logFile, $baseUrl);
    }

    public function testParsesAllUrlsFromFixture(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        $this->assertCount(95, $result);
    }

    public function testOutputIsSortedAndUnique(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        $sorted = $result;
        sort($sorted);

        $this->assertSame($sorted, $result, 'URLs should be sorted');
        $this->assertSame(array_unique($result), $result, 'URLs should be deduplicated');
    }

    public function testOutputContainsExpectedPaths(): void
    {
        $fixture = __DIR__ . '/../fixtures/crawl.log';
        $result = $this->parse($fixture);

        // Spot-check a few known paths from the fixture
        $this->assertContains('/', $result);
        $this->assertContains('/about', $result);
        $this->assertContains('/blog', $result);
        $this->assertContains('/user/login', $result);
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

        $this->assertSame(2, count($result));
        $this->assertSame(['/other', '/page'], $result);
    }

    public function testIgnoresLinesFromOtherHosts(): void
    {
        $log = "$this->tempDir/mixed.log";
        file_put_contents($log, implode("\n", [
            '2026-01-01 00:00:01 URL:https://example.com/page [100] -> "clone/page" [1]',
            '2026-01-01 00:00:02 URL:https://other.com/page [100] -> "clone/page" [1]',
        ]));

        $result = $this->parse($log);

        $this->assertSame(1, count($result));
        $this->assertSame(['/page'], $result);
    }

    public function testReturnsZeroForMissingLogFile(): void
    {
        $result = $this->parse("$this->tempDir/nonexistent.log");

        $this->assertSame(0, count($result));
        $this->assertSame([], $result);
    }
}
