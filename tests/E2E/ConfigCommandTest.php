<?php

namespace Tests\E2E;

/**
 * E2E tests for ConfigCommand
 */
class ConfigCommandTest extends CommandTestCase
{
    public function testDisplaysConfigurationFromFile(): void
    {
        $this->fixture->writeConfig([
            'environments' => ['local' => ['url' => 'http://localhost:1234']],
            'profiles' => ['admin' => ['username' => 'adminuser', 'password' => 'secret']],
        ]);

        $this->executeCommand('config');
        $this->assertCommandSuccess();

        $this->assertOutputContains('environments:');
        $this->assertOutputContains('local');
        $this->assertOutputContains('http://localhost:1234');
    }

    public function testShowsMessageWhenNoConfigFile(): void
    {
        // No config written — fixture dir exists but no config.yaml
        $this->executeCommand('config');
        $this->assertCommandSuccess();
        $this->assertOutputContains('Configuration file not found');
    }

    public function testShowsErrorOnInvalidYaml(): void
    {
        $this->fixture->writeInvalidYamlConfig();
        $this->executeCommand('config');
        $this->assertCommandFailure(1);
        $this->assertOutputContains('Error reading config file');
    }
}

