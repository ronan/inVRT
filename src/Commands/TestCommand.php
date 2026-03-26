<?php

namespace App\Commands;

class TestCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('test')
            ->setDescription('Run visual regression tests')
            ->setHelp('Runs visual regression tests comparing current screenshots against reference screenshots.');
        parent::configure();
    }

    protected function getScriptName(): string
    {
        return 'invrt-test.sh';
    }
}
