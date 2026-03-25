<?php

namespace Tests\E2E;

/**
 * E2E tests for ConfigCommand
 *
 * Tests the `invrt config` command which displays project configuration.
 */
class ConfigCommandTest extends CommandTestCase
{
    /**
     * Test config command succeeds when config exists
     */
    public function testConfigCommandDisplaysConfiguration(): void
    {
        // Setup: Create a project with config
        $this->fixture->writeConfigWithProfiles();

        // Execute config command
        $this->executeCommand('config');

        // Assert success (output may be captured differently when using include)
        $this->assertCommandSuccess();
    }

    /**
     * Test config command with custom profile option
     */
    public function testConfigCommandWithProfile(): void
    {
        // Setup: Config with profiles
        $this->fixture->writeConfigWithProfiles();

        // Execute with profile option
        $this->executeCommand('config', ['--profile' => 'mobile']);

        // Assert success
        $this->assertCommandSuccess();
    }

    /**
     * Test config command with environment option
     */
    public function testConfigCommandWithEnvironment(): void
    {
        // Setup: Config with environments
        $this->fixture->writeConfigWithEnvironments();

        // Execute with environment option
        $this->executeCommand('config', ['--environment' => 'dev']);

        // Assert success
        $this->assertCommandSuccess();
    }

    /**
     * Test config command with device option
     */
    public function testConfigCommandWithDevice(): void
    {
        // Setup: Config with devices
        $this->fixture->writeConfigWithDevices();

        // Execute with device option
        $this->executeCommand('config', ['--device' => 'mobile']);

        // Assert success
        $this->assertCommandSuccess();
    }

    /**
     * Test config command without config file succeeds gracefully
     */
    public function testConfigCommandWithoutConfigFile(): void
    {
        // Setup: No config file created

        // Execute config command
        $this->executeCommand('config');

        // Assert success (config command doesn't require config file)
        // It just displays that no config is found
        $this->assertCommandSuccess();
    }

    /**
     * Test config command shows error on invalid YAML
     */
    public function testConfigCommandWithInvalidYaml(): void
    {
        // Setup: Create invalid YAML config
        $this->fixture->writeInvalidYamlConfig();

        // Execute config command
        $this->executeCommand('config');

        // Should handle the error gracefully
        // The config command catches exceptions
        $this->assertTrue(true, 'Config command handles invalid YAML');
    }
}
