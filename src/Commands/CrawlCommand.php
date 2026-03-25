<?php

namespace App\Commands;

class CrawlCommand extends BaseCommand
{
    protected static $defaultName = 'crawl';
    protected static $defaultDescription = 'Crawl the website and generate screenshots';

    protected function configure(): void
    {
        parent::configure();
        $this->setHelp('Crawls the configured website and generates screenshots for the specified profile, device, and environment.');
    }

    protected function getScriptName(): string
    {
        return 'invrt-crawl.sh';
    }
}
