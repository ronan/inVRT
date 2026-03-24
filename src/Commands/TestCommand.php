<?php

namespace App\Commands;

class TestCommand extends BaseCommand
{
    protected static $defaultName = 'test';
    protected static $defaultDescription = 'Run visual regression tests';

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Runs visual regression tests comparing current screenshots against reference screenshots.');
    }

    protected function getScriptName(): string
    {
        return 'invrt-test.sh';
    }
}

