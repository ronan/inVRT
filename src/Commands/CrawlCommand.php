<?php

namespace App\Commands;

class CrawlCommand extends BaseCommand
{
    protected function configure(): void
    {
        $this
            ->setName('crawl')
            ->setDescription('Crawl the website and generate screenshots')
            ->setHelp('Crawls the configured website and generates screenshots for the specified profile, device, and environment.');
        parent::configure();
    }

    protected function getScriptName(): string
    {
        return 'invrt-crawl.sh';
    }
}
