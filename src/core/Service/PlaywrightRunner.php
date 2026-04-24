<?php

namespace InVRT\Core\Service;

use InVRT\Core\Configuration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/** Runs Playwright in either 'reference' (capture) or 'test' (compare) mode. */
class PlaywrightRunner
{
    public function __construct(
        private readonly Configuration $config,
        private readonly LoggerInterface $logger,
    ) {}

    public function run(string $mode): int
    {
        $configFile = $this->config->get('INVRT_PLAYWRIGHT_CONFIG_FILE', '');
        $specFile   = $this->config->get('INVRT_PLAYWRIGHT_SPEC_FILE', '');
        $configDir  = $configFile !== '' ? dirname($configFile) : null;

        $cmd = 'npx playwright test';
        if ($configFile !== '') {
            $cmd .= ' --config=' . escapeshellarg($configFile);
        }
        if ($specFile !== '') {
            $cmd .= ' ' . escapeshellarg($specFile);
        }
        if ($mode === 'reference') {
            $cmd .= ' --update-snapshots';
        }

        $this->logger->debug('Running Playwright command: ' . $cmd);
        $this->logger->notice('Running playwright test' . ($mode === 'reference' ? ' --update-snapshots' : ''));

        $process = Process::fromShellCommandline($cmd, $configDir, $this->config->all());
        $process->setTimeout(null);

        $parser = new NodeOutputParser($this->logger);
        $stdout = '';
        $process->run(function (mixed $type, mixed $buffer) use ($parser, &$stdout): void {
            if ($type === Process::ERR) {
                $parser->write($buffer);
                return;
            }
            $stdout .= $buffer;
            foreach (explode("\n", $buffer) as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $this->logger->notice($line);
                }
            }
        });
        $parser->flush();

        $exitCode = $process->getExitCode() ?? 0;
        $this->logger->debug('Playwright exit code: ' . $exitCode);

        $this->writeResultsFile($mode, $stdout . $parser->getMessages());

        return $exitCode;
    }

    private function writeResultsFile(string $mode, string $output): void
    {
        $file = match ($mode) {
            'reference' => $this->config->get('INVRT_REFERENCE_FILE', ''),
            'test'      => $this->config->get('INVRT_TEST_FILE', ''),
            default     => '',
        };
        if ($file === '') {
            return;
        }
        Filesystem::writeFile($file, $output);
    }
}
