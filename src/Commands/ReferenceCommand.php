<?php

namespace App\Commands;

class ReferenceCommand extends BaseCommand
{
    protected static $defaultName = 'reference';
    protected static $defaultDescription = 'Create reference screenshots for comparison';

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Creates reference screenshots for the specified profile, device, and environment to use as baseline for comparison.');
    }

    protected function getScriptName(): string
    {
        return 'invrt-reference.sh';
    }
}
