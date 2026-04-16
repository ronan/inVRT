<?php

namespace Tests\E2E;

class BaselineCommandTest extends WebCommandTestCase
{
    public function testBaselineCommandBuildsAndApprovesArtifacts(): void
    {
        $this->fixture->deleteInvrtDirectory();
        $this->executeCommandWithInputs('baseline', [$this->webserverUrl()]);

        $this->assertCommandSuccess();
        $this->assertOutputContains('No configuration file found. Initializing inVRT first.');
        $this->assertOutputContains('No reference screenshots found');
        $this->assertOutputContains('No test screenshots found');
        $this->assertOutputContains('Approving latest results');

        $bitmapsDir = $this->fixture->getInvrtDir() . '/data/local/anonymous/desktop/bitmaps';
        $this->assertDirectoryExists($bitmapsDir . '/reference');
        $this->assertDirectoryExists($bitmapsDir . '/test');
    }
}
