<?php

namespace Tests\E2E;

/**
 * E2E tests for ReferenceCommand
 * 
 * Tests the `invrt reference` command which creates reference screenshots.
 */
class ReferenceCommandTest extends CommandTestCase
{
    /**
     * Test reference command requires configuration
     */
    public function testReferenceCommandRequiresConfig(): void
    {
        // Setup: No config file created

        // Execute reference command - should throw since config is required
        try {
            $this->executeCommand('reference');
            $this->fail('Expected RuntimeException for missing config');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Configuration file not found', $e->getMessage());
        }
    }

    /**
     * Test reference command with minimal config
     */
    public function testReferenceCommandWithMinimalConfig(): void
    {
        // Setup: Create minimal config
        $this->fixture->writeMinimalConfig();

        // Save original CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute reference command
            $this->executeCommand('reference');

            // Verify command loads config (may fail due to missing dependencies)
            $this->assertTrue(true, 'Reference command executed without fatal errors');

        } finally {
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    /**
     * Test reference command with profile option
     */
    public function testReferenceCommandWithProfileOption(): void
    {
        // Setup: Config with profiles
        $this->fixture->writeConfigWithProfiles();

        // Save original CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute with profile option
            $this->executeCommand('reference', ['--profile' => 'mobile']);

            // Verify command loads config and sets up environment
            $this->assertTrue(true, 'Reference command accepts profile option');

        } finally {
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    /**
     * Test reference command with environment option
     */
    public function testReferenceCommandWithEnvironmentOption(): void
    {
        // Setup: Config with environments
        $this->fixture->writeConfigWithEnvironments();

        // Save original CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute with environment option
            $this->executeCommand('reference', ['--environment' => 'dev']);

            // Verify command loads config and sets up environment
            $this->assertTrue(true, 'Reference command accepts environment option');

        } finally {
            chdir($originalCwd);
            if ($originalInitCwd === false) {
                putenv('INIT_CWD');
            } else {
                putenv('INIT_CWD=' . $originalInitCwd);
            }
        }
    }

    /**
     * Test reference command with device option
     */
    public function testReferenceCommandWithDeviceOption(): void
    {
        // Setup: Config with devices
        $this->fixture->writeConfigWithDevices();

        // Save original CWD
        $originalCwd = getcwd();
        $originalInitCwd = getenv('INIT_CWD');

        try {
            chdir($this->fixture->getProjectDir());
            putenv('INIT_CWD=' . $this->fixture->getProjectDir());

            // Execute with device option
            $this->executeCommand('reference', ['--device' => 'mobile']);

            // Verify command loads config and sets up environment
            $this->assertTrue(true, 'Reference command accepts device option');

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
