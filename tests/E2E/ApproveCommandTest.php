<?php

namespace Tests\E2E;

class ApproveCommandTest extends WebCommandTestCase
{
    public function testApproveCommandSucceedsAfterTestRun(): void
    {
        $this->setUpFixture(true);
        $this->fixture->writeConfig([
            'environments' => [
                'local' => ['url' => $this->webserverUrl()],
            ],
        ]);

        $this->executeCommand('test');
        $this->assertCommandSuccess();

        $this->executeCommand('approve');
        $this->assertCommandSuccess();
        $this->assertOutputContains('Approving latest results');
    }
}
