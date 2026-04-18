<?php

namespace Tests\E2E;

use Symfony\Component\Yaml\Yaml;

class CheckCommandTest extends WebCommandTestCase
{
    public function testCheckWritesCheckYaml(): void
    {
        $this->executeCommand('check');

        $this->assertCommandSuccess();

        $checkFile = $this->fixture->getInvrtDir() . '/data/local/check.yaml';
        $this->assertFileExists($checkFile);

        $data = Yaml::parseFile($checkFile);

        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('https', $data);
        $this->assertArrayHasKey('checked_at', $data);

        $this->assertEquals('Home', $data['title']);
        $this->assertFalse($data['https']);
        $this->assertStringStartsWith('http://127.0.0.1:', $data['url']);
    }

    public function testCheckOutputsSuccessMessage(): void
    {
        $this->executeCommand('check');

        $this->assertCommandSuccess();
        $this->assertOutputContains('Site check complete');
        $this->assertOutputContains('Home');
    }

    public function testCheckFailsWhenSiteUnreachable(): void
    {
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => 'http://127.0.0.1:19999'],
            ],
        ]);

        $this->executeCommand('check');

        $this->assertCommandFailure();
    }

    public function testCheckYamlHasNoRedirectedFromWhenNoRedirect(): void
    {
        $this->executeCommand('check');

        $this->assertCommandSuccess();

        $checkFile = $this->fixture->getInvrtDir() . '/data/local/check.yaml';
        $data = Yaml::parseFile($checkFile);

        $this->assertArrayNotHasKey('redirected_from', $data);
    }
}
