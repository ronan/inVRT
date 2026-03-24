<?php

namespace App\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Command
{
    protected static $defaultName = 'init';
    protected static $defaultDescription = 'Initialize a new inVRT project in the current directory';

    protected function configure(): void
    {
        $this->setHelp('Initializes a new inVRT project with the default configuration structure.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $scriptPath = __DIR__ . '/../invrt-init.sh';
        
        $exitCode = null;
        passthru('bash ' . escapeshellarg($scriptPath), $exitCode);
        
        return $exitCode ?? Command::SUCCESS;
    }
}
