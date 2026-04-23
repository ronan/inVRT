<?php

namespace Tests\Unit;

use InVRT\Core\Service\NodeOutputParser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class NodeOutputParserTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private NodeOutputParser $parser;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->parser = new NodeOutputParser($this->logger);
    }

    public function testInfoLineRoutedToNoticeLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('notice')
            ->with('hello world');

        $this->parser->write('{"level":30,"msg":"hello world"}' . "\n");
        $this->parser->flush();
    }

    public function testDebugLineRoutedToDebugLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('low level detail');

        $this->parser->write('{"level":20,"msg":"low level detail"}' . "\n");
        $this->parser->flush();
    }

    public function testTraceLineRoutedToDebugLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('trace msg');

        $this->parser->write('{"level":10,"msg":"trace msg"}' . "\n");
        $this->parser->flush();
    }

    public function testWarnLineRoutedToWarningLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('something odd');

        $this->parser->write('{"level":40,"msg":"something odd"}' . "\n");
        $this->parser->flush();
    }

    public function testErrorLineRoutedToErrorLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('error')
            ->with('it broke');

        $this->parser->write('{"level":50,"msg":"it broke"}' . "\n");
        $this->parser->flush();
    }

    public function testFatalLineRoutedToEmergencyLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('emergency')
            ->with('fatal crash');

        $this->parser->write('{"level":60,"msg":"fatal crash"}' . "\n");
        $this->parser->flush();
    }

    public function testNonJsonLineRoutedToDebugLogger(): void
    {
        $this->logger->expects($this->once())
            ->method('debug')
            ->with('[Backstop] some raw output');

        $this->parser->write('[Backstop] some raw output' . "\n");
        $this->parser->flush();
    }

    public function testPartialLineBufferedAcrossWrites(): void
    {
        $this->logger->expects($this->once())
            ->method('notice')
            ->with('split message');

        $this->parser->write('{"level":30,"msg":');
        $this->parser->write('"split message"}' . "\n");
        $this->parser->flush();
    }

    public function testFlushProcessesRemainingPartialLine(): void
    {
        $this->logger->expects($this->once())
            ->method('notice')
            ->with('no newline');

        $this->parser->write('{"level":30,"msg":"no newline"}');
        $this->parser->flush();
    }

    public function testGetMessagesContainsNoticeAndAbove(): void
    {
        $this->logger->method('debug');
        $this->logger->method('notice');
        $this->logger->method('warning');

        $this->parser->write('{"level":20,"msg":"debug msg"}' . "\n");
        $this->parser->write('{"level":30,"msg":"info msg"}' . "\n");
        $this->parser->write('{"level":40,"msg":"warn msg"}' . "\n");
        $this->parser->flush();

        $messages = $this->parser->getMessages();
        $this->assertStringNotContainsString('debug msg', $messages);
        $this->assertStringContainsString('info msg', $messages);
        $this->assertStringContainsString('warn msg', $messages);
    }

    public function testEmptyLinesAreSkipped(): void
    {
        $this->logger->expects($this->never())->method($this->anything());

        $this->parser->write("\n\n\n");
        $this->parser->flush();
    }
}
