<?php

namespace Tests\E2E;

/**
 * E2E tests for InitCommand
 * 
 * Tests the `invrt init` command which initializes a new inVRT project.
 */
class InitCommandTest extends CommandTestCase
{
    /**
     * Recursively remove directory and contents
     */
    private function rmdirRecursive(string $dir): bool
    {
        if (!is_dir($dir)) {
            return @unlink($dir);
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $this->rmdirRecursive("$dir/$file");
        }

        return @rmdir($dir);
    }

    /**
     * Test init command creates project structure
     */
    public function testInitCommandCreatesProjectStructure(): void
    {
        // Remove the .invrt directory that fixture created - we want init to create it
        $invrtDir = $this->fixture->getInvrtDir();
        if (is_dir($invrtDir)) {
            $this->rmdirRecursive($invrtDir);
        }

        // Save original CWD and INIT_CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            // Setup: Change to project directory and set INIT_CWD
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute init command with output capture
            $this->executeCommandWithOutputCapture('init');

            // Assert command succeeded
            $this->assertCommandSuccess();

            // Verify subprocess output contains initialization messages
            $this->assertStrayOutputHasInitMessages();
            $this->assertStrayOutputContains('Created invrt directory');

            // Assert .invrt directory was created
            $this->assertTrue(is_dir($this->fixture->getInvrtDir()),
                '.invrt directory was not created');

            // Assert config.yaml was created
            $this->assertConfigFileExists();

            // Assert data directory was created
            $dataDir = $this->fixture->getInvrtDir() . '/data';
            $this->assertTrue(is_dir($dataDir), 'data directory was not created');

            // Assert config contains expected sections
            $config = $this->fixture->readConfig();
            $this->assertArrayHasKey('environments', $config);
            $this->assertArrayHasKey('profiles', $config);
            $this->assertArrayHasKey('devices', $config);

        } finally {
            // Restore original CWD and INIT_CWD
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    /**
     * Test init command fails when already initialized
     */
    public function testInitCommandFailsWhenAlreadyInitialized(): void
    {
        // Save original CWD and INIT_CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            // Setup: Change to project directory and initialize
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // The fixture already created .invrt, so init should fail
            $this->executeCommand('init');
            
            // Must have failed with exit code 1
            $this->assertCommandFailure(1);

        } finally {
            // Restore original CWD and INIT_CWD
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    /**
     * Test init command creates valid config structure
     */
    public function testInitCommandCreatesValidConfig(): void
    {
        // Remove the .invrt directory that fixture created - we want init to create it
        $invrtDir = $this->fixture->getInvrtDir();
        if (is_dir($invrtDir)) {
            $this->rmdirRecursive($invrtDir);
        }

        // Save original CWD and INIT_CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            // Setup
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute init command
            $this->executeCommand('init');
            $this->assertCommandSuccess();

            // Read the generated config
            $config = $this->fixture->readConfig();

            // Verify config structure
            $this->assertIsArray($config);
            $this->assertArrayHasKey('environments', $config);
            $this->assertArrayHasKey('profiles', $config);
            $this->assertArrayHasKey('devices', $config);

            // Verify environments section
            $environments = $config['environments'];
            $this->assertArrayHasKey('local', $environments);
            $this->assertArrayHasKey('dev', $environments);
            $this->assertArrayHasKey('prod', $environments);

            // Verify profiles section
            $profiles = $config['profiles'];
            $this->assertArrayHasKey('anonymous', $profiles);
            $this->assertArrayHasKey('admin', $profiles);

            // Verify devices section
            $devices = $config['devices'];
            $this->assertArrayHasKey('desktop', $devices);

        } finally {
            // Restore original CWD and INIT_CWD
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }
}
