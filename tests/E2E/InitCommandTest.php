<?php

namespace Tests\E2E;

class InitCommandTest extends CommandTestCase
{
    public function testInitWritesPassedUrlToSelectedEnvironment(): void
    {
        $this->fixture->deleteInvrtDirectory();
        $this->executeCommand('init', [
            'url' => 'https://example.test',
            '--environment' => 'stage',
        ]);

        $this->assertCommandSuccess();
        $this->assertConfigFileExists();
        $this->assertConfigValue('environments.stage.url', 'https://example.test');
        $this->assertConfigValue('profiles.anonymous', []);
        $this->assertConfigValue('devices.desktop', []);
    }

    public function testInitPromptsForUrlWhenArgumentIsMissing(): void
    {
        $this->fixture->deleteInvrtDirectory();
        $this->executeCommandWithInputs('init', ['https://prompted.example'], [
            '--environment' => 'review',
            '--profile' => 'editor',
            '--device' => 'tablet',
        ]);

        $this->assertCommandSuccess();
        $this->assertOutputContains('What URL should inVRT use?');
        $this->assertConfigValue('environments.review.url', 'https://prompted.example');
        $this->assertConfigValue('profiles.editor', []);
        $this->assertConfigValue('devices.tablet', []);
    }
}
