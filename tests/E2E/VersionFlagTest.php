<?php

namespace Tests\E2E;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class VersionFlagTest extends TestCase
{
    public function testVersionFlagPrintsCurrentVersion(): void
    {
        $root = dirname(__DIR__, 2);
        $versionProcess = new Process(['task', 'version:show'], $root);
        $versionProcess->run();

        $this->assertTrue(
            $versionProcess->isSuccessful(),
            $versionProcess->getErrorOutput() . $versionProcess->getOutput(),
        );

        $lines = preg_split('/\r?\n/', trim($versionProcess->getOutput())) ?: [];
        $expectedVersion = null;
        for ($i = count($lines) - 1; $i >= 0; $i -= 1) {
            $line = trim($lines[$i]);
            if ($line !== '') {
                $expectedVersion = $line;
                break;
            }
        }

        $this->assertNotNull($expectedVersion, 'task version:show must output a version');

        $process = new Process(['php', 'bin/invrt', '--version'], $root);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput() . $process->getOutput());

        $output = trim($process->getOutput());
        $this->assertStringContainsString('inVRT CLI', $output);
        $this->assertStringContainsString((string) $expectedVersion, $output);
    }
}
