<?php

namespace App\Commands;

class ReferenceCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('reference')
            ->setDescription('Create reference screenshots for comparison')
            ->setHelp('Creates reference screenshots for the specified profile, device, and environment to use as baseline for comparison.');
        parent::configure();
    }

    protected function getScriptName(): string
    {
        return 'invrt-reference.sh';
    }
}
