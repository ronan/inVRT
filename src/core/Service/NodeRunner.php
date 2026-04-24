<?php

namespace InVRT\Core\Service;

use InVRT\Core\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/** Runs Node.js scripts from the app's JS directory with pino NDJSON log routing. */
class NodeRunner
{
    public function __construct(
        private readonly Configuration $config,
        private readonly string $appDir,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Run a Node.js script.
     *
     * Streams $inputFile content to stdin if provided. Captures stdout and
     * writes it to $outputFile if provided. Log messages arrive on stderr as
     * pino NDJSON and are routed to the PSR-3 logger.
     */
    public function run(string $script, ?string $inputFile = null, ?string $outputFile = null): int
    {
        [$exit, $stdout] = $this->runCapturing($script, $inputFile);

        if ($outputFile !== null && $outputFile !== '') {
            $this->logger->debug("Writing output to $outputFile");
            Filesystem::writeFile($outputFile, $stdout);
        }

        return $exit;
    }

    /**
     * Run a Node.js script and return [exitCode, stdout].
     *
     * @return array{0: int, 1: string}
     */
    public function runCapturing(string $script, ?string $inputFile = null): array
    {
        $file = rtrim($this->appDir, '/') . '/' . $script;
        $cmd  = 'node ' . escapeshellarg($file);
        $this->logger->debug("Running Node script: $cmd");

        $process = Process::fromShellCommandline($cmd, null, $this->config->all());
        $process->setTimeout(null);

        if ($inputFile !== null && is_readable($inputFile)) {
            $process->setInput(file_get_contents($inputFile));
        }

        $parser = new NodeOutputParser($this->logger);
        $stdout = '';
        $process->run(function (mixed $type, mixed $buffer) use ($parser, &$stdout): void {
            if ($type === Process::ERR) {
                $parser->write($buffer);
            } else {
                $stdout .= $buffer;
            }
        });
        $parser->flush();

        return [$process->getExitCode() ?? 0, $stdout];
    }
}
