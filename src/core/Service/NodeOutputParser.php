<?php

namespace InVRT\Core\Service;

use Psr\Log\LoggerInterface;

/**
 * Parses NDJSON output from Node.js scripts using pino and routes each log
 * line to the PSR-3 logger at the appropriate level.
 *
 * Non-JSON lines (e.g. backstop's own output) are forwarded as debug.
 */
class NodeOutputParser
{
    /** Incomplete line fragment carried between write() calls. */
    private string $buffer = '';

    /** Accumulated info+ messages for results files. */
    private string $messages = '';

    public function __construct(private readonly LoggerInterface $logger) {}

    /** Feed a raw output chunk from the Node process. */
    public function write(string $chunk): void
    {
        $this->buffer .= $chunk;
        $lines = explode("\n", $this->buffer);
        // Last element is incomplete — keep it in the buffer.
        $this->buffer = (string) array_pop($lines);
        foreach ($lines as $line) {
            $this->parseLine($line);
        }
    }

    /** Flush any buffered partial line (call after process exits). */
    public function flush(): void
    {
        if ($this->buffer !== '') {
            $this->parseLine($this->buffer);
            $this->buffer = '';
        }
    }

    /** Human-readable messages at info level and above (for results files). */
    public function getMessages(): string
    {
        return $this->messages;
    }

    private function parseLine(string $line): void
    {
        $line = trim($line);
        if ($line === '') {
            return;
        }

        $data = json_decode($line, true);

        if (!is_array($data)) {
            $this->logger->debug($line);
            return;
        }

        $msg   = isset($data['msg']) && is_string($data['msg']) ? $data['msg'] : $line;
        $level = isset($data['level']) && is_int($data['level']) ? $data['level'] : 30;

        match (true) {
            $level >= 60 => $this->logger->emergency($msg),
            $level >= 50 => $this->logger->error($msg),
            $level >= 40 => $this->logger->warning($msg),
            $level >= 30 => $this->logger->info($msg),
            default      => $this->logger->debug($msg),
        };

        if ($level >= 30) {
            $this->messages .= $msg . "\n";
        }
    }
}
