<?php

namespace Tests\E2E;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * E2E tests for InitCommand
 *
 * Tests the `invrt init` command which initializes a new inVRT project.
 */
class InitCommandTest extends CommandTestCase
{
    private function rmdirRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return @unlink($dir);
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $this->rmdirRecursive("$dir/$file");
        }
        return @rmdir($dir);
    }

    public function testInitCommandCreatesProject(): void
    {
        $invrtDir = $this->fixture->getInvrtDir();
        if (is_dir($invrtDir)) {
            $this->rmdirRecursive($invrtDir);
        }

        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            $this->executeCommand('init', [], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
            $this->assertCommandSuccess();

            // Output
            $this->assertOutputContains('Initializing InVRT');
            $this->assertOutputContains('Created invrt directory');

            // Directory structure
            $this->assertTrue(is_dir($this->fixture->getInvrtDir()), '.invrt directory was not created');
            $this->assertConfigFileExists();
            $this->assertTrue(is_dir($this->fixture->getInvrtDir() . '/data'), 'data directory was not created');

            // Config structure
            $config = $this->fixture->readConfig();
            $this->assertArrayHasKey('environments', $config);
            $this->assertArrayHasKey('profiles', $config);
            $this->assertArrayHasKey('devices', $config);
            $this->assertArrayHasKey('local', $config['environments']);
            $this->assertArrayHasKey('anonymous', $config['profiles']);
            $this->assertArrayHasKey('desktop', $config['devices']);
        } finally {
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    public function testInitCommandFailsWhenAlreadyInitialized(): void
    {
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            $this->executeCommand('init');
            $this->assertCommandFailure(1);
        } finally {
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }
}
